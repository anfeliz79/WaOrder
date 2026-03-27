<?php

namespace App\Services\Conversation\Handlers;

use App\Models\ChatSession;
use App\Models\Order;
use App\Services\Order\OrderOrchestrator;

class OrderActiveHandler implements HandlerInterface
{
    public function handle(ChatSession $session, string $message, string $messageType): array
    {
        $order = null;

        if ($session->active_order_id) {
            $order = Order::find($session->active_order_id);
        }

        if (!$order) {
            $order = Order::where('customer_phone', $session->customer_phone)
                ->where('tenant_id', $session->tenant_id)
                ->active()
                ->latest()
                ->first();
        }

        if (!$order) {
            return [
                'response' => "No tienes pedidos activos en este momento.\n\nQuieres hacer un nuevo pedido?",
                'response_type' => 'buttons',
                'buttons' => [
                    ['id' => 'opt_menu', 'title' => 'Ver el menu'],
                ],
                'next_state' => 'greeting',
            ];
        }

        // Check if order reached terminal state
        if ($order->isTerminal()) {
            $status = $order->status === 'delivered' ? 'entregado' : 'cancelado';
            return [
                'response' => "Tu pedido #{$order->order_number} ha sido {$status}.\n\nGracias por tu preferencia! Escribe cuando quieras pedir de nuevo.",
                'next_state' => 'greeting',
                'active_order_id' => null,
                'destroy_session' => true,
            ];
        }

        $messageLower = mb_strtolower(trim($message));

        // Handle cancel request
        if (in_array($messageLower, ['active_cancel', 'cancelar', 'cancelar pedido', 'cancel'])) {
            return $this->handleCancelRequest($order);
        }

        // Handle cancel confirmation
        if (in_array($messageLower, ['cancel_confirm_yes', 'si cancelar'])) {
            return $this->performCancellation($order);
        }

        if (in_array($messageLower, ['cancel_confirm_no', 'no cancelar'])) {
            return $this->showOrderStatus($order);
        }

        // Handle status check
        if (in_array($messageLower, ['active_status', 'estado', 'como va', 'como va mi pedido'])) {
            return $this->showOrderStatus($order);
        }

        // Handle "contact driver" button from out_for_delivery notification
        if (str_starts_with($messageLower, 'contact_driver_')) {
            return $this->handleContactDriver($order);
        }

        // Handle "track" button from out_for_delivery notification (same as status check)
        if (str_starts_with($messageLower, 'track_')) {
            return $this->showOrderStatus($order);
        }

        // Default: show status with options
        return $this->showOrderStatus($order);
    }

    private function showOrderStatus(Order $order): array
    {
        $statusLabels = [
            'confirmed' => 'confirmado y esperando preparacion',
            'in_preparation' => 'siendo preparado en este momento',
            'ready' => 'listo! Pronto sera despachado',
            'out_for_delivery' => 'en camino hacia ti',
        ];

        $statusEmoji = [
            'confirmed' => "\u{2705}",
            'in_preparation' => "\u{1F468}\u{200D}\u{1F373}",
            'ready' => "\u{1F4E6}",
            'out_for_delivery' => "\u{1F6F5}",
        ];

        $emoji = $statusEmoji[$order->status] ?? "\u{1F4CB}";
        $statusText = $statusLabels[$order->status] ?? $order->status;

        $response = "{$emoji} Tu pedido *#{$order->order_number}* esta {$statusText}.\n\nTe avisaremos cuando haya un cambio.";

        // Build buttons based on order status
        $buttons = [];

        // Can cancel only if confirmed (not yet being prepared)
        if ($order->status === 'confirmed') {
            $buttons[] = ['id' => 'active_cancel', 'title' => 'Cancelar pedido'];
        }

        $buttons[] = ['id' => 'active_status', 'title' => 'Actualizar estado'];

        $result = [
            'response' => $response,
            'response_type' => 'buttons',
            'buttons' => array_slice($buttons, 0, 3),
        ];

        $callCta = $this->buildCallCta();
        if ($callCta) {
            $result['post_messages'] = [$callCta];
        }

        return $result;
    }

    private function handleCancelRequest(Order $order): array
    {
        // Can only cancel if order is 'confirmed' (not yet in preparation)
        if ($order->status !== 'confirmed') {
            $statusLabels = [
                'in_preparation' => 'ya esta siendo preparado',
                'ready' => 'ya esta listo',
                'out_for_delivery' => 'ya esta en camino',
            ];

            $reason = $statusLabels[$order->status] ?? 'ya esta en proceso';

            $result = [
                'response' => "Lo sentimos, tu pedido #{$order->order_number} {$reason} y no puede ser cancelado en este momento.\n\nSi necesitas ayuda, contacta al restaurante.",
                'response_type' => 'buttons',
                'buttons' => [
                    ['id' => 'active_status', 'title' => 'Ver estado'],
                ],
            ];

            $callCta = $this->buildCallCta();
            if ($callCta) {
                $result['post_messages'] = [$callCta];
            }

            return $result;
        }

        return [
            'response' => "Estas seguro que deseas cancelar tu pedido #{$order->order_number}?\n\nEsta accion no se puede deshacer.",
            'response_type' => 'buttons',
            'buttons' => [
                ['id' => 'cancel_confirm_yes', 'title' => 'Si, cancelar'],
                ['id' => 'cancel_confirm_no', 'title' => 'No, mantener'],
            ],
        ];
    }

    private function performCancellation(Order $order): array
    {
        if ($order->status !== 'confirmed') {
            return $this->showOrderStatus($order);
        }

        try {
            $orchestrator = app(OrderOrchestrator::class);
            $orchestrator->transition($order, 'cancel', 'customer', null, 'Cancelado por el cliente via WhatsApp');

            return [
                'response' => "Tu pedido #{$order->order_number} ha sido cancelado.\n\nLamentamos que no pudieramos atenderte esta vez. Escribe cuando quieras pedir de nuevo!",
                'next_state' => 'greeting',
                'active_order_id' => null,
                'destroy_session' => true,
            ];
        } catch (\Exception $e) {
            $result = [
                'response' => "No pudimos cancelar tu pedido en este momento. Por favor contacta al restaurante.",
                'response_type' => 'text',
            ];

            $callCta = $this->buildCallCta();
            if ($callCta) {
                $result['post_messages'] = [$callCta];
            }

            return $result;
        }
    }

    private function handleContactDriver(Order $order): array
    {
        $order->loadMissing('driver');
        $driver = $order->driver;

        if (!$driver) {
            return $this->showOrderStatus($order);
        }

        $phone = preg_replace('/[^0-9]/', '', $driver->phone);
        $waLink = "https://wa.me/{$phone}";

        return [
            'response' => "\xF0\x9F\x9B\xB5 *{$driver->name}* esta llevando tu pedido.\n\n\xF0\x9F\x93\x9E Puedes contactarlo aqui:",
            'response_type' => 'cta_url',
            'cta_body' => "\xF0\x9F\x9B\xB5 *{$driver->name}* esta llevando tu pedido.",
            'cta_button_text' => 'Contactar mensajero',
            'cta_url' => $waLink,
        ];
    }

    private function buildCallCta(): ?array
    {
        $tenant = app('tenant');
        $restaurantPhone = $tenant->getSetting('restaurant_phone');

        if (!$restaurantPhone) {
            return null;
        }

        $cleanPhone = preg_replace('/[^0-9+]/', '', $restaurantPhone);
        if (!str_starts_with($cleanPhone, '+')) {
            $cleanPhone = '+' . $cleanPhone;
        }

        return [
            'type' => 'cta_url',
            'body' => "\u{260E}\u{FE0F} Para llamar al restaurante:",
            'button_text' => 'Llamar restaurante',
            'url' => "tel:{$cleanPhone}",
        ];
    }
}
