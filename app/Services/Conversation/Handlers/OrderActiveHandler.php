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
                'response' => "No tienes pedidos activos ahora mismo. ¿Te animas a hacer uno? 😊",
                'response_type' => 'buttons',
                'buttons' => [
                    ['id' => 'opt_order', 'title' => 'Hacer mi pedido'],
                ],
                'next_state' => 'greeting',
            ];
        }

        // Check if order reached terminal state
        if ($order->isTerminal()) {
            $terminalMsg = $order->status === 'delivered'
                ? "¡Tu pedido *#{$order->order_number}* fue entregado! 🎉 Esperamos que lo hayas disfrutado. Escríbenos cuando quieras pedir de nuevo."
                : "Tu pedido *#{$order->order_number}* fue cancelado. 😔 Si necesitas ayuda, con gusto te atendemos. Escríbenos cuando quieras.";

            return [
                'response' => $terminalMsg,
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
        $statusMessages = [
            'confirmed' => "✅ Tu pedido *#{$order->order_number}* fue confirmado y está en fila para ser preparado. ¡Ya casi!",
            'in_preparation' => "👨‍🍳 ¡Buenas noticias! Tu pedido *#{$order->order_number}* ya está siendo preparado. Nuestros cocineros están en eso.",
            'ready' => "📦 ¡Tu pedido *#{$order->order_number}* está listo! En breve sale para donde estás.",
            'out_for_delivery' => "🛵 ¡Tu pedido *#{$order->order_number}* ya salió y va en camino hacia ti! No te muevas. 😄",
        ];

        $response = $statusMessages[$order->status]
            ?? "📋 Tu pedido *#{$order->order_number}* — estado: {$order->status}";

        $response .= "\n\nTe avisamos de cualquier cambio.";

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

        // Contextual CTA: driver contact when out for delivery, restaurant call otherwise
        if ($order->status === 'out_for_delivery') {
            $order->loadMissing('driver');
            $cta = $order->driver
                ? $this->buildDriverCta($order->driver)
                : $this->buildCallCta();
        } else {
            $cta = $this->buildCallCta();
        }

        if ($cta) {
            $result['post_messages'] = [$cta];
        }

        return $result;
    }

    private function handleCancelRequest(Order $order): array
    {
        // Can only cancel if order is 'confirmed' (not yet in preparation)
        if ($order->status !== 'confirmed') {
            $statusReasons = [
                'in_preparation' => 'ya está siendo preparado 👨‍🍳',
                'ready' => 'ya está listo para salir 📦',
                'out_for_delivery' => 'ya viene en camino 🛵',
            ];

            $reason = $statusReasons[$order->status] ?? 'ya está en proceso';

            $result = [
                'response' => "Lo sentimos, tu pedido *#{$order->order_number}* {$reason} y ya no es posible cancelarlo.\n\nSi necesitas ayuda, con gusto te atendemos.",
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
            'response' => "¿Estás seguro de que deseas cancelar tu pedido *#{$order->order_number}*?\n\nEsta acción no se puede deshacer.",
            'response_type' => 'buttons',
            'buttons' => [
                ['id' => 'cancel_confirm_yes', 'title' => 'Sí, cancelar'],
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
                'response' => "Tu pedido *#{$order->order_number}* fue cancelado. 😔\n\nLamentamos que no pudiéramos atenderte esta vez. ¡Escríbenos cuando quieras pedir de nuevo!",
                'next_state' => 'greeting',
                'active_order_id' => null,
                'destroy_session' => true,
            ];
        } catch (\Exception $e) {
            $result = [
                'response' => "No pudimos cancelar tu pedido en este momento. Por favor contáctanos directamente.",
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
            'body' => "📞 ¿Tienes alguna pregunta sobre tu pedido? Llámanos:",
            'button_text' => 'Llamar al restaurante',
            'url' => "tel:{$cleanPhone}",
        ];
    }

    private function buildDriverCta(\App\Models\Driver $driver): array
    {
        $phone = preg_replace('/[^0-9]/', '', $driver->phone);
        $waLink = "https://wa.me/{$phone}";

        return [
            'type' => 'cta_url',
            'body' => "🛵 *{$driver->name}* está de camino con tu pedido. ¿Necesitas contactarlo?",
            'button_text' => 'Llamar al repartidor',
            'url' => $waLink,
        ];
    }
}
