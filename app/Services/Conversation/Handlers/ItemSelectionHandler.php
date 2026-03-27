<?php

namespace App\Services\Conversation\Handlers;

use App\Models\ChatSession;
use App\Services\AI\AiService;
use App\Services\Menu\MenuSearcher;
use App\Services\Menu\MenuService;
use App\Services\WhatsApp\MessageFactory;

class ItemSelectionHandler implements HandlerInterface
{
    public function handle(ChatSession $session, string $message, string $messageType): array
    {
        $menuService = app(MenuService::class);
        $message = trim($message);
        $context = $session->context_data ?? [];
        $categoryItems = $context['current_category_items'] ?? [];

        // Handle list reply - direct item selection by ID (e.g. "item_5", "item_-123456")
        $item = null;
        $quantity = 1;

        if (preg_match('/^item_(-?\d+)$/', $message, $matches)) {
            $item = $menuService->findItemById($matches[1]);
        }

        if (!$item) {
            // Parse quantity and item from free text like "2 de la pepperoni grande" or "dame 3"
            $itemText = $message;

            if (preg_match('/^(?:dame\s+)?(\d+)\s*(?:de\s+(?:la|el|los|las)\s*)?(.+)$/iu', $message, $matches)) {
                $quantity = (int) $matches[1];
                $itemText = trim($matches[2]);
            } elseif (preg_match('/^(\d+)$/u', $message, $matches)) {
                // Just a number - refers to item index
                $itemText = $message;
            }

            // Try to find the item
            $item = $menuService->findItemByNameOrIndex($itemText, $categoryItems);

            // If not found, try fuzzy match
            if (!$item && $categoryItems) {
                $item = MenuSearcher::fuzzyMatch($itemText, $categoryItems);
            }

            // If still not found, search globally
            if (!$item) {
                $results = $menuService->searchItems($itemText);
                $item = $results[0] ?? null;
            }

            // Last resort: ask AI to match from current category items
            if (!$item && $categoryItems) {
                $ai = app(AiService::class);
                if ($ai->isAvailable()) {
                    $names     = array_column($categoryItems, 'name');
                    $aiName    = $ai->matchMenuItem($itemText, $names);
                    if ($aiName) {
                        $item = $menuService->findItemByNameOrIndex($aiName, $categoryItems);
                    }
                }
            }
        }

        if (!$item) {
            $retryCount = ($context['retry_count'] ?? 0) + 1;

            if ($retryCount >= 3) {
                // After 3 retries, show categories again
                return [
                    'response' => "No encontre ese producto. Volvamos a las categorias.\n\n" .
                        MessageFactory::menuCategoriesText($menuService->getCategories()),
                    'next_state' => 'menu_browsing',
                    'context_data' => array_merge($context, ['retry_count' => 0]),
                ];
            }

            return [
                'response' => "No encontre \"{$message}\" en el menu. Escribe el numero del item o su nombre.\n\n" .
                    ($categoryItems ? MessageFactory::categoryItemsText('Menu', $categoryItems) : ''),
                'context_data' => array_merge($context, ['retry_count' => $retryCount]),
            ];
        }

        // Check if item has variants or optionals - redirect to modifier selection
        $modifiers = $item['modifiers'] ?? [];
        if (!empty($modifiers['variant_groups']) || !empty($modifiers['optional_groups'])) {
            // Standard text-based flow for modifier selection
            $modContext = array_merge($context, [
                'pending_item' => [
                    'menu_item_id' => $item['id'],
                    'name' => $item['name'],
                    'quantity' => $quantity,
                    'base_price' => $item['price'],
                    'modifiers_config' => $modifiers,
                ],
                'modifier_step' => !empty($modifiers['variant_groups']) ? 'variant' : 'optional',
                'current_variant_group_index' => 0,
                'current_optional_group_index' => 0,
                'selected_variants' => [],
                'selected_optionals' => [],
                'retry_count' => 0,
            ]);

            $firstPrompt = !empty($modifiers['variant_groups'])
                ? MessageFactory::variantGroupPrompt($item['name'], $modifiers['variant_groups'][0])
                : MessageFactory::optionalGroupPrompt($item['name'], $modifiers['optional_groups'][0]);

            return [
                'response' => "Has seleccionado *{$item['name']}*" .
                    ($quantity > 1 ? " (x{$quantity})" : '') . ".\n\n" . $firstPrompt,
                'next_state' => 'modifier_selection',
                'context_data' => $modContext,
            ];
        }

        // Add item to cart (no modifiers - direct add)
        $cart = $session->cart_data ?? ['items' => [], 'subtotal' => 0, 'delivery_fee' => 0, 'total' => 0];
        $tenant = app('tenant');
        $unitPrice = $tenant->applyTax($item['price']);

        // Check if item already in cart (same item, no modifiers)
        $found = false;
        foreach ($cart['items'] as &$cartItem) {
            if ($cartItem['menu_item_id'] == $item['id'] && empty($cartItem['modifiers']['variants']) && empty($cartItem['modifiers']['optionals'])) {
                $cartItem['quantity'] += $quantity;
                $cartItem['subtotal'] = $cartItem['quantity'] * $cartItem['unit_price'];
                $found = true;
                break;
            }
        }
        unset($cartItem);

        if (!$found) {
            $cart['items'][] = [
                'menu_item_id' => $item['id'],
                'name' => $item['name'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'modifiers' => [],
                'subtotal' => $quantity * $unitPrice,
            ];
        }

        // Recalculate totals
        $cart['subtotal'] = array_sum(array_column($cart['items'], 'subtotal'));
        $cart['total'] = $cart['subtotal'] + ($cart['delivery_fee'] ?? 0);

        $price = number_format($unitPrice * $quantity, 0, '.', ',');

        return [
            'response' => "Perfecto! Agregado:\n{$quantity}x {$item['name']} - RD\${$price}\n\n" .
                MessageFactory::cartSummaryText($cart['items'], $cart['subtotal']) .
                "\n\nTip: Escribe 2 o la cantidad deseada + nombre del producto para agregar mas cantidad.",
            'response_type' => 'buttons',
            'buttons' => [
                ['id' => 'cart_add', 'title' => 'Agregar mas'],
                ['id' => 'cart_checkout', 'title' => 'Hacer pedido'],
                ['id' => 'cart_remove', 'title' => 'Eliminar item'],
            ],
            'next_state' => 'cart_review',
            'cart_data' => $cart,
            'context_data' => array_merge($context, ['retry_count' => 0]),
        ];
    }
}
