<?php

namespace App\Jobs;

use App\Models\Driver;
use App\Models\MessageLog;
use App\Models\Order;
use App\Models\Tenant;
use App\Jobs\SendWhatsAppNotification;
use App\Services\Conversation\ConversationEngine;
use App\Services\Driver\DriverMessageHandler;
use App\Services\Session\SessionManager;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;
    public int $timeout = 30;

    public function __construct(
        public int $tenantId,
        public string $from,
        public string $type,
        public string $content,
        public ?string $messageId,
        public ?string $timestamp,
    ) {}

    public function handle(
        SessionManager $sessionManager,
        ConversationEngine $conversationEngine,
        WhatsAppClient $whatsAppClient,
    ): void {
        // Deduplication check
        if ($this->messageId) {
            $dedupeKey = "wa_msg:{$this->messageId}";
            if (Cache::has($dedupeKey)) {
                Log::info('Duplicate WhatsApp message skipped', ['message_id' => $this->messageId]);
                return;
            }
            Cache::put($dedupeKey, true, 3600); // 1 hour
        }

        $tenant = Tenant::find($this->tenantId);
        if (!$tenant) {
            Log::error('ProcessWhatsAppMessage: Tenant not found', ['tenant_id' => $this->tenantId]);
            return;
        }

        // Bind tenant to app container
        app()->instance('tenant', $tenant);

        // Block bot if tenant has no active subscription
        if (!$tenant->isBotEnabled()) {
            Log::info('ProcessWhatsAppMessage: bot disabled (no active subscription)', ['tenant_id' => $this->tenantId]);
            return;
        }

        // Route driver button presses to DriverMessageHandler
        if (str_starts_with($this->content, 'drv_')) {
            $driver = Driver::where('phone', $this->from)
                ->where('tenant_id', $this->tenantId)
                ->where('is_active', true)
                ->first();

            if ($driver) {
                MessageLog::create([
                    'tenant_id' => $this->tenantId,
                    'driver_id' => $driver->id,
                    'direction' => 'inbound',
                    'customer_phone' => $this->from,
                    'message_type' => $this->type,
                    'content' => $this->content,
                    'meta_message_id' => $this->messageId,
                ]);

                app(DriverMessageHandler::class)->handle($tenant, $driver, $this->content);
                return;
            }
        }

        // Handle customer button: contact driver
        if (str_starts_with($this->content, 'contact_driver_')) {
            $orderId = (int) str_replace('contact_driver_', '', $this->content);
            $order = Order::where('id', $orderId)->where('tenant_id', $this->tenantId)->first();

            if ($order && $order->driver_id) {
                $driver = Driver::find($order->driver_id);
                if ($driver) {
                    $phone = preg_replace('/[^0-9]/', '', $driver->phone);
                    $message = "📞 *Tu mensajero: {$driver->name}*\n\n"
                        . "Puedes contactarlo directamente:\n"
                        . "📱 WhatsApp: https://wa.me/{$phone}\n"
                        . "📞 Llamar: {$driver->phone}";

                    SendWhatsAppNotification::dispatch($this->tenantId, $this->from, $message);
                    return;
                }
            }

            SendWhatsAppNotification::dispatch($this->tenantId, $this->from, 'No se encontro informacion del mensajero.');
            return;
        }

        // Handle customer button: track order
        if (str_starts_with($this->content, 'track_')) {
            $orderId = (int) str_replace('track_', '', $this->content);
            $order = Order::where('id', $orderId)->where('tenant_id', $this->tenantId)->first();

            if ($order) {
                $statusLabels = [
                    'confirmed' => 'Confirmado',
                    'in_preparation' => 'En preparacion',
                    'ready' => 'Listo',
                    'out_for_delivery' => 'En camino',
                    'delivered' => 'Entregado',
                    'cancelled' => 'Cancelado',
                ];
                $label = $statusLabels[$order->status] ?? $order->status;
                $message = "📦 *Pedido #{$order->order_number}*\n\n"
                    . "Estado actual: *{$label}*";

                if ($order->driver_id) {
                    $driver = Driver::find($order->driver_id);
                    if ($driver) {
                        $message .= "\nMensajero: {$driver->name}";
                    }
                }

                SendWhatsAppNotification::dispatch($this->tenantId, $this->from, $message);
                return;
            }

            SendWhatsAppNotification::dispatch($this->tenantId, $this->from, 'Pedido no encontrado.');
            return;
        }

        // Log inbound message
        $session = $sessionManager->getOrCreate($this->tenantId, $this->from);

        MessageLog::create([
            'tenant_id' => $this->tenantId,
            'session_id' => $session->id,
            'direction' => 'inbound',
            'customer_phone' => $this->from,
            'message_type' => $this->type,
            'content' => $this->content,
            'meta_message_id' => $this->messageId,
        ]);

        // Process through conversation engine
        $result = $conversationEngine->process($session, $this->content, $this->type);

        // Send pre-messages (images, CTA buttons) before the main response
        if (!empty($result['pre_messages'])) {
            foreach ($result['pre_messages'] as $preMsg) {
                $preType = $preMsg['type'] ?? 'text';
                match ($preType) {
                    'image' => $whatsAppClient->sendImageMessage(
                        $tenant,
                        $this->from,
                        $preMsg['image_url'],
                        $preMsg['caption'] ?? null,
                    ),
                    'cta_url' => $whatsAppClient->sendCtaUrlButton(
                        $tenant,
                        $this->from,
                        $preMsg['body'],
                        $preMsg['button_text'],
                        $preMsg['url'],
                    ),
                    default => $whatsAppClient->sendTextMessage(
                        $tenant,
                        $this->from,
                        $preMsg['body'] ?? '',
                    ),
                };
                // Small delay between messages to avoid rate limits
                usleep(200_000); // 200ms
            }
        }

        // Send response based on response_type
        if ($result['response']) {
            $responseType = $result['response_type'] ?? 'text';
            $messageType = 'text';

            switch ($responseType) {
                case 'buttons':
                    if (!empty($result['buttons'])) {
                        $whatsAppClient->sendInteractiveButtons(
                            $tenant,
                            $this->from,
                            $result['response'],
                            $result['buttons']
                        );
                        $messageType = 'interactive_buttons';
                    } else {
                        $whatsAppClient->sendTextMessage($tenant, $this->from, $result['response']);
                    }
                    break;

                case 'list':
                    if (!empty($result['list_sections'])) {
                        $whatsAppClient->sendInteractiveList(
                            $tenant,
                            $this->from,
                            $result['response'],
                            $result['list_button_text'] ?? 'Ver opciones',
                            $result['list_sections']
                        );
                        $messageType = 'interactive_list';
                    } else {
                        $whatsAppClient->sendTextMessage($tenant, $this->from, $result['response']);
                    }
                    break;

                case 'cta_url':
                    $whatsAppClient->sendCtaUrlButton(
                        $tenant,
                        $this->from,
                        $result['cta_body'] ?? $result['response'],
                        $result['cta_button_text'] ?? 'Abrir',
                        $result['cta_url'],
                    );
                    $messageType = 'cta_url';
                    break;

                default:
                    $whatsAppClient->sendTextMessage($tenant, $this->from, $result['response']);
                    break;
            }

            // Log outbound message
            MessageLog::create([
                'tenant_id' => $this->tenantId,
                'session_id' => $session->id,
                'direction' => 'outbound',
                'customer_phone' => $this->from,
                'message_type' => $messageType,
                'content' => $result['response'],
                'ai_used' => $result['ai_used'] ?? false,
                'ai_model' => $result['ai_model'] ?? null,
                'ai_tokens_used' => $result['ai_tokens'] ?? null,
            ]);
        }

        // Send post-messages (CTA buttons, etc.) after the main response
        if (!empty($result['post_messages'])) {
            foreach ($result['post_messages'] as $postMsg) {
                usleep(300_000); // 300ms between messages
                $postType = $postMsg['type'] ?? 'text';
                match ($postType) {
                    'cta_url' => $whatsAppClient->sendCtaUrlButton(
                        $tenant,
                        $this->from,
                        $postMsg['body'],
                        $postMsg['button_text'],
                        $postMsg['url'],
                    ),
                    default => $whatsAppClient->sendTextMessage(
                        $tenant,
                        $this->from,
                        $postMsg['body'] ?? '',
                    ),
                };
            }
        }
    }
}
