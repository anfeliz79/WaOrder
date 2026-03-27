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

        // Check if customer has an active order
        $activeOrder = Order::where('customer_phone', $session->customer_phone)
            ->where('tenant_id', $session->tenant_id)
            ->active()
            ->first();

        if ($activeOrder) {
            $statusLabels = [
                'confirmed' => 'confirmado',
                'in_preparation' => 'en preparacion',
                'ready' => 'listo',
                'out_for_delivery' => 'en camino',
            ];

            $statusText = $statusLabels[$activeOrder->status] ?? $activeOrder->status;

            $result = [
                'response' => "Hola! Tienes un pedido activo #{$activeOrder->order_number} ({$statusText}). Que deseas hacer?",
                'response_type' => 'buttons',
                'buttons' => [
                    ['id' => 'active_status', 'title' => 'Ver mi pedido'],
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

        $customerName = $session->customer?->name;
        $greeting = $customerName
            ? "Hola {$customerName}! Que gusto verte de nuevo por *{$restaurantName}*. Como podemos ayudarte hoy?"
            : "Hola! Bienvenido a *{$restaurantName}*. Estamos para servirte, que te gustaria hacer?";

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
                'cta_button_text' => 'Ver menu',
                'cta_url' => $menuUrl,
                'next_state' => 'cart_review',
                'context_data' => array_merge($session->context_data ?? [], ['web_menu_token' => $token]),
            ];

            $callCta = $this->buildCallCta($tenant);
            if ($callCta) {
                $result['post_messages'] = [$callCta];
            }

            return $result;
        }

        $result = [
            'response' => $greeting,
            'response_type' => 'buttons',
            'buttons' => [
                ['id' => 'opt_menu', 'title' => 'Ver el menu'],
                ['id' => 'opt_status', 'title' => 'Estado de pedido'],
            ],
            'next_state' => 'menu_browsing',
        ];

        $callCta = $this->buildCallCta($tenant);
        if ($callCta) {
            $result['post_messages'] = [$callCta];
        }

        return $result;
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
            'body' => "\u{260E}\u{FE0F} Para llamar al restaurante:",
            'button_text' => 'Llamar restaurante',
            'url' => "tel:{$cleanPhone}",
        ];
    }
}
