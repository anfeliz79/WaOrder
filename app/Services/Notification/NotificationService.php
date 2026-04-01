<?php

namespace App\Services\Notification;

use App\Jobs\SendWhatsAppNotification;
use App\Models\ChatSession;
use App\Models\Order;
use App\Models\SurveyResponse;
use App\Models\Tenant;
use App\Services\WhatsApp\MessageFactory;
use Illuminate\Support\Facades\Log;

class NotificationService
{
    public function notifyCustomerStatusChange(Order $order, string $newStatus): void
    {
        if ($newStatus === 'delivered') {
            $this->sendDeliveryWithSurvey($order);
            return;
        }

        if ($newStatus === 'out_for_delivery') {
            $this->sendOutForDelivery($order);
            return;
        }

        $message = MessageFactory::orderStatusText($order->order_number, $newStatus);

        SendWhatsAppNotification::dispatch(
            $order->tenant_id,
            $order->customer_phone,
            $message,
        );

        Log::info('Customer notification queued', [
            'order_id' => $order->id,
            'status' => $newStatus,
            'phone' => $order->customer_phone,
        ]);
    }

    private function sendOutForDelivery(Order $order): void
    {
        $order->loadMissing('driver');
        $driver = $order->driver;

        $message = "🛵 ¡Tu pedido *#{$order->order_number}* ya salió y va en camino hacia ti!";

        if ($driver) {
            $message .= "\n\n👤 Tu mensajero es *{$driver->name}*. Ya queda poco. 😊";
        } else {
            $message .= "\n\nYa queda poco. Te avisamos cuando sea entregado.";
        }

        $buttons = null;
        if ($driver) {
            $buttons = [
                ['id' => "contact_driver_{$order->id}", 'title' => '📞 Contactar Delivery'],
            ];
        }

        SendWhatsAppNotification::dispatch(
            $order->tenant_id,
            $order->customer_phone,
            $message,
            $buttons,
        );

        Log::info('Out for delivery notification with driver info queued', [
            'order_id' => $order->id,
            'driver_id' => $driver?->id,
        ]);
    }

    public function notifyOrderConfirmed(Order $order): void
    {
        $this->notifyCustomerStatusChange($order, 'confirmed');
    }

    /**
     * Send a payment link to the customer via WhatsApp CTA URL button.
     */
    public function sendPaymentLink(Order $order, Tenant $tenant, string $paymentUrl): void
    {
        $message = "Para completar tu pedido *#{$order->order_number}*, realiza el pago con tarjeta a traves del siguiente enlace.\n\n"
            . "Total: *" . number_format($order->total, 2) . " {$tenant->currency}*\n\n"
            . "El enlace expira en 30 minutos.";

        // Send as text with URL (WhatsApp Cloud API doesn't support CTA URL buttons in regular messages)
        $messageWithLink = $message . "\n\n" . $paymentUrl;

        SendWhatsAppNotification::dispatch(
            $order->tenant_id,
            $order->customer_phone,
            $messageWithLink,
        );

        Log::info('Payment link sent to customer', [
            'order_id' => $order->id,
            'url' => $paymentUrl,
        ]);
    }

    /**
     * Send payment confirmation to the customer.
     */
    public function sendPaymentConfirmation(Order $order, Tenant $tenant): void
    {
        $message = "¡Pago recibido! Tu pedido *#{$order->order_number}* por *"
            . number_format($order->total, 2) . " {$tenant->currency}* ha sido pagado exitosamente.\n\n"
            . "Estamos preparando tu pedido.";

        SendWhatsAppNotification::dispatch(
            $order->tenant_id,
            $order->customer_phone,
            $message,
        );

        Log::info('Payment confirmation sent', ['order_id' => $order->id]);
    }

    private function sendDeliveryWithSurvey(Order $order): void
    {
        $tenant = Tenant::find($order->tenant_id);
        $surveyEnabled = data_get($tenant?->settings, 'survey.enabled', true);

        if (!$surveyEnabled) {
            // No survey — send plain delivery message
            SendWhatsAppNotification::dispatch(
                $order->tenant_id,
                $order->customer_phone,
                MessageFactory::orderStatusText($order->order_number, 'delivered'),
            );

            Log::info('Delivery notification queued (no survey)', ['order_id' => $order->id]);
            return;
        }

        // Create survey response record (skip if already exists for this order)
        $survey = SurveyResponse::firstOrCreate(
            ['order_id' => $order->id],
            [
                'tenant_id' => $order->tenant_id,
                'customer_id' => $order->customer_id,
                'customer_phone' => $order->customer_phone,
            ],
        );

        // If the survey record already existed, the notification was already dispatched before.
        // This guard prevents duplicate messages from double-taps or job retries.
        if (!$survey->wasRecentlyCreated) {
            Log::info('Delivery survey notification already dispatched for this order — skipping duplicate', [
                'order_id' => $order->id,
                'survey_id' => $survey->id,
            ]);
            return;
        }

        // Update or create session in survey state
        $session = ChatSession::where('tenant_id', $order->tenant_id)
            ->where('customer_phone', $order->customer_phone)
            ->where('status', 'active')
            ->first();

        if ($session) {
            $context = $session->context_data ?? [];
            $context['survey_step'] = 'rating';
            $context['survey_id'] = $survey->id;

            $session->update([
                'conversation_state' => 'survey',
                'context_data' => $context,
                'expires_at' => now()->addHours(2),
            ]);
        } else {
            // Create a new session in survey state
            $session = ChatSession::create([
                'tenant_id' => $order->tenant_id,
                'customer_phone' => $order->customer_phone,
                'conversation_state' => 'survey',
                'status' => 'active',
                'context_data' => [
                    'survey_step' => 'rating',
                    'survey_id' => $survey->id,
                ],
                'expires_at' => now()->addHours(2),
            ]);
        }

        // Send delivery message + survey rating buttons in a single message
        $message = "¡Tu pedido *#{$order->order_number}* fue entregado! 🎉 ¡Gracias por elegirnos!\n\n"
            . "Nos encantaría saber cómo te fue. ¿Cómo calificarías tu experiencia de hoy?";

        $buttons = [
            ['id' => 'rate_5', 'title' => "\u{2B50}\u{2B50}\u{2B50}\u{2B50}\u{2B50} (5)"],
            ['id' => 'rate_4', 'title' => "\u{2B50}\u{2B50}\u{2B50}\u{2B50} (4)"],
            ['id' => 'rate_3', 'title' => "\u{2B50}\u{2B50}\u{2B50} (3 o menos)"],
        ];

        SendWhatsAppNotification::dispatch(
            $order->tenant_id,
            $order->customer_phone,
            $message,
            $buttons,
        );

        Log::info('Delivery notification with survey queued', [
            'order_id' => $order->id,
            'survey_id' => $survey->id,
        ]);
    }
}
