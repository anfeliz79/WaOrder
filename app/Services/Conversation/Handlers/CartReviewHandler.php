<?php

namespace App\Services\Conversation\Handlers;

use App\Models\ChatSession;
use App\Services\Menu\MenuService;
use App\Services\Menu\MenuTokenService;
use App\Services\WhatsApp\MessageFactory;

class CartReviewHandler implements HandlerInterface
{
    public function handle(ChatSession $session, string $message, string $messageType): array
    {
        $message = mb_strtolower(trim($message));
        $cart = $session->cart_data ?? ['items' => [], 'subtotal' => 0];

        // Handle remove_{index} button press (e.g. "remove_0", "remove_2")
        if (preg_match('/^remove_(\d+)$/', $message, $matches)) {
            $index = (int) $matches[1];
            if (isset($cart['items'][$index])) {
                $removed = $cart['items'][$index]['name'];
                array_splice($cart['items'], $index, 1);
                $cart['subtotal'] = array_sum(array_column($cart['items'], 'subtotal'));
                $cart['total'] = $cart['subtotal'] + ($cart['delivery_fee'] ?? 0);

                $response = "✅ Eliminado: {$removed}";

                if (empty($cart['items'])) {
                    return [
                        'response' => $response . "\n\nTu carrito quedó vacío. ¿Quieres seguir explorando el menú?",
                        'response_type' => 'buttons',
                        'buttons' => [['id' => 'opt_order', 'title' => 'Ver el menú']],
                        'cart_data' => $cart,
                        'next_state' => 'menu_browsing',
                        'context_data' => array_merge($session->context_data ?? [], ['awaiting_removal' => false]),
                    ];
                }

                // Show updated cart with summary
                $result = $this->cartSummaryWithButtons($cart, $session);
                $result['pre_messages'] = [['type' => 'text', 'body' => $response]];
                return $result;
            }
        }

        // Option 1: Add more (button ID or text)
        if (in_array($message, ['cart_add', 'agregar', 'mas', 'agregar mas', 'agregar mas productos'])) {
            $tenant = app('tenant');

            // For external menus, send web menu link instead of category list
            if ($tenant->getMenuSource() === 'external') {
                $tokenService = app(MenuTokenService::class);
                $token = $tokenService->generateMenuToken(
                    $tenant->id,
                    $session->id,
                    $session->customer_phone,
                );
                $menuUrl = $tokenService->buildMenuUrl($token);

                return [
                    'response' => '¡Claro! Agrega más productos desde el menú:',
                    'response_type' => 'cta_url',
                    'cta_body' => '🍽️ ¡Agrega más productos a tu pedido!',
                    'cta_button_text' => 'Ver menú',
                    'cta_url' => $menuUrl,
                    'context_data' => array_merge($session->context_data ?? [], ['web_menu_token' => $token]),
                ];
            }

            $menuService = app(MenuService::class);
            $categories = $menuService->getCategories();

            if (empty($categories)) {
                return [
                    'response' => 'El menú no está disponible en este momento. Intenta de nuevo en unos minutos.',
                    'response_type' => 'text',
                ];
            }

            $rows = [];
            foreach ($categories as $cat) {
                $rows[] = [
                    'id' => 'cat_' . $cat['id'],
                    'title' => substr($cat['name'], 0, 24),
                    'description' => isset($cat['description']) ? substr($cat['description'], 0, 72) : '',
                ];
            }

            return [
                'response' => '🍽️ Elige una categoría para agregar más productos:',
                'response_type' => 'list',
                'list_button_text' => 'Ver categorías',
                'list_sections' => [['title' => 'Categorías', 'rows' => $rows]],
                'next_state' => 'menu_browsing',
            ];
        }

        // Option 2: Continue to checkout (button ID or text)
        if (in_array($message, ['cart_checkout', 'continuar', 'confirmar', 'confirmar pedido', 'pedir', 'continuar al pedido', 'checkout'])) {
            if (empty($cart['items'])) {
                return [
                    'response' => 'Tu carrito está vacío. Agrega productos primero. 😊',
                    'response_type' => 'text',
                    'next_state' => 'menu_browsing',
                ];
            }

            $info = $session->collected_info ?? [];

            // Determine what info we still need.
            // Fall back to customer.name from previous orders if not in collected_info.
            $customerName = $info['name'] ?? $session->customer?->name;

            if (empty($customerName)) {
                return [
                    'response' => '¿Cómo te llamas? 😊',
                    'response_type' => 'text',
                    'next_state' => 'collecting_info',
                    'context_data' => array_merge($session->context_data ?? [], ['awaiting_field' => 'name']),
                ];
            }

            // Name is known — pre-fill from Customer record if collected_info was wiped
            if (empty($info['name'])) {
                $info['name'] = $customerName;
            }

            return [
                'response' => '¿Cómo deseas recibir tu pedido?',
                'response_type' => 'buttons',
                'buttons' => [
                    ['id' => 'info_delivery', 'title' => '🛵 Delivery'],
                    ['id' => 'info_pickup', 'title' => '🏪 Recoger'],
                ],
                'next_state' => 'collecting_info',
                'collected_info' => $info,
                'context_data' => array_merge($session->context_data ?? [], ['awaiting_field' => 'delivery_type']),
            ];
        }

        // Option 3b: Cancel everything and close session
        if (in_array($message, ['cart_cancel', 'cancelar', 'cancelar todo', 'cancelar pedido', 'cerrar', 'cerrar sesion', 'salir', 'no quiero'])) {
            return [
                'response' => "Tu pedido fue cancelado. 👍 No hay problema. Escríbenos cuando quieras pedir de nuevo.",
                'response_type' => 'text',
                'destroy_session' => true,
            ];
        }

        // Option 3: Remove item (button ID or text) — show items as interactive buttons/list
        if (in_array($message, ['cart_remove', 'eliminar', 'quitar', 'eliminar un producto'])) {
            if (empty($cart['items'])) {
                return ['response' => 'Tu carrito esta vacio.', 'response_type' => 'text'];
            }

            return $this->removeItemMenu($cart, $session);
        }

        // Option 4: Clear cart
        if (in_array($message, ['4', 'vaciar', 'vaciar carrito', 'limpiar'])) {
            return [
                'response' => 'Carrito vaciado. ¿Quieres ver el menú de nuevo?',
                'response_type' => 'buttons',
                'buttons' => [
                    ['id' => 'opt_order', 'title' => 'Ver el menú'],
                ],
                'cart_data' => ['items' => [], 'subtotal' => 0, 'delivery_fee' => 0, 'total' => 0],
                'next_state' => 'menu_browsing',
            ];
        }

        // Default: show cart again with buttons
        if (empty($cart['items'])) {
            return [
                'response' => 'Tu carrito está vacío. 🛒',
                'response_type' => 'buttons',
                'buttons' => [
                    ['id' => 'opt_order', 'title' => 'Ver el menú'],
                ],
                'next_state' => 'menu_browsing',
            ];
        }

        return $this->cartSummaryWithButtons($cart, $session);
    }

    private function cartSummaryWithButtons(array $cart, ChatSession $session): array
    {
        $text = MessageFactory::cartSummaryText($cart['items'], $cart['subtotal']);
        $text .= "\n\n_Para cancelar todo el pedido escribe *cancelar*._";

        return [
            'response' => $text,
            'response_type' => 'buttons',
            'buttons' => [
                ['id' => 'cart_add',      'title' => 'Agregar mas'],
                ['id' => 'cart_checkout', 'title' => 'Confirmar pedido'],
                ['id' => 'cart_remove',   'title' => 'Eliminar item'],
            ],
            'cart_data' => $cart,
            'context_data' => array_merge($session->context_data ?? [], ['awaiting_removal' => false]),
        ];
    }

    private function removeItemMenu(array $cart, ChatSession $session): array
    {
        $items = $cart['items'];

        // Use buttons if 3 or fewer items, list if more
        if (count($items) <= 3) {
            $buttons = [];
            foreach ($items as $i => $item) {
                $modDesc = MessageFactory::modifierDescription($item['modifiers'] ?? []);
                $label = $item['name'];
                if ($modDesc) {
                    $label .= " ({$modDesc})";
                }
                $buttons[] = [
                    'id' => "remove_{$i}",
                    'title' => mb_substr($label, 0, 20),
                ];
            }

            return [
                'response' => 'Selecciona el producto que quieres eliminar:',
                'response_type' => 'buttons',
                'buttons' => $buttons,
                'context_data' => array_merge($session->context_data ?? [], ['awaiting_removal' => false]),
            ];
        }

        // More than 3 items — use list message
        $rows = [];
        foreach ($items as $i => $item) {
            $modDesc = MessageFactory::modifierDescription($item['modifiers'] ?? []);
            $title = mb_substr($item['name'], 0, 24);
            $desc = '';
            if ($modDesc) {
                $desc = mb_substr($modDesc, 0, 72);
            }
            $price = 'RD$' . number_format($item['subtotal'], 0, '.', ',');
            $desc = $desc ? "{$desc} — {$price}" : $price;

            $rows[] = [
                'id' => "remove_{$i}",
                'title' => $title,
                'description' => mb_substr($desc, 0, 72),
            ];
        }

        return [
            'response' => 'Selecciona el producto que quieres eliminar:',
            'response_type' => 'list',
            'list_button_text' => 'Ver productos',
            'list_sections' => [['title' => 'Tu pedido', 'rows' => $rows]],
            'context_data' => array_merge($session->context_data ?? [], ['awaiting_removal' => false]),
        ];
    }
}
