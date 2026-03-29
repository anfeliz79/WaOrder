<?php

namespace App\Services\Conversation\Handlers;

use App\Models\ChatSession;
use App\Models\Order;
use App\Services\Menu\MenuService;
use App\Services\Menu\MenuTokenService;

class GreetingHandler implements HandlerInterface
{
    public function handle(ChatSession $session, string $message, string $messageType): array
    {
        $tenant = app('tenant');
        $restaurantName = $tenant->name ?? 'nuestro restaurante';
        $customerName = $session->customer?->name;
        $hasPhone = !empty($tenant->getSetting('restaurant_phone'));

        // Check if customer has an active order
        $activeOrder = Order::where('customer_phone', $session->customer_phone)
            ->where('tenant_id', $session->tenant_id)
            ->active()
            ->first();

        if ($activeOrder) {
            $statusLabels = [
                'confirmed' => 'confirmado ✅',
                'in_preparation' => 'en preparacion 👨‍🍳',
                'ready' => 'listo para salir 📦',
                'out_for_delivery' => 'en camino 🛵',
            ];

            $statusText = $statusLabels[$activeOrder->status] ?? $activeOrder->status;
            $name = $customerName ? "¡Hola {$customerName}! 👋 " : '¡Hola! 👋 ';

            $result = [
                'response' => "{$name}Veo que tienes tu pedido *#{$activeOrder->order_number}* {$statusText}. ¿Qué deseas hacer?",
                'response_type' => 'buttons',
                'buttons' => [
                    ['id' => 'active_status', 'title' => 'Ver estado'],
                    ['id' => 'active_new', 'title' => 'Nuevo pedido'],
                ],
                'next_state' => 'order_active',
                'active_order_id' => $activeOrder->id,
            ];

            $callCta = $this->buildCallCta($tenant);
            if ($callCta) {
                $result['post_messages'] = [$callCta];
            }

            return $result;
        }

        // Greeting text — personalized for returning customers
        $greeting = $customerName
            ? "¡Hola {$customerName}! 😊 Qué gusto tenerte de vuelta en *{$restaurantName}*. ¿Cómo te podemos ayudar?"
            : "¡Hola! 👋 Bienvenido a *{$restaurantName}*. Qué bueno que estás aquí. ¿En qué te podemos ayudar?";

        // For external menus, send CTA URL button to open the web menu client
        if ($tenant->getMenuSource() === 'external') {
            $tokenService = app(MenuTokenService::class);
            $token = $tokenService->generateMenuToken(
                $tenant->id,
                $session->id,
                $session->customer_phone,
            );
            $menuUrl = $tokenService->buildMenuUrl($token);

            $result = [
                'response' => $greeting,
                'response_type' => 'cta_url',
                'cta_body' => $greeting,
                'cta_button_text' => '🛒 Ver el menu',
                'cta_url' => $menuUrl,
                'next_state' => 'cart_review',
                'context_data' => array_merge($session->context_data ?? [], ['web_menu_token' => $token]),
            ];

            // Offer phone option as follow-up message
            $callCta = $this->buildCallCta($tenant);
            if ($callCta) {
                $result['post_messages'] = [$callCta];
            }

            return $result;
        }

        // Build main buttons: always offer ordering, optionally offer calling
        $buttons = [
            ['id' => 'opt_order', 'title' => 'Hacer mi pedido'],
        ];

        if ($hasPhone) {
            $buttons[] = ['id' => 'opt_call', 'title' => 'Llamar al local'];
        }

        return [
            'response' => $greeting,
            'response_type' => 'buttons',
            'buttons' => $buttons,
            'next_state' => 'menu_browsing',
        ];
    }

    private function buildCallCta(mixed $tenant): ?array
    {
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
            'body' => "📞 ¿Prefieres hablar con nosotros?",
            'button_text' => 'Llamar al restaurante',
            'url' => "tel:{$cleanPhone}",
        ];
    }
}
