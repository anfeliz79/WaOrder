<?php

namespace App\Services\Conversation\Handlers;

use App\Models\ChatSession;
use App\Services\Menu\MenuSearcher;
use App\Services\Menu\MenuTokenService;
use App\Services\WhatsApp\MessageFactory;

class ModifierSelectionHandler implements HandlerInterface
{
    public function handle(ChatSession $session, string $message, string $messageType): array
    {
        $message = trim($message);
        $messageLower = mb_strtolower($message);
        $context = $session->context_data ?? [];
        $pendingItem = $context['pending_item'] ?? null;

        if (!$pendingItem) {
            return [
                'response' => 'Ocurrio un error. Volvamos al menu.',
                'next_state' => 'menu_browsing',
            ];
        }

        // Handle web_customize button - send CTA URL to web menu
        if ($messageLower === 'web_customize' || $messageLower === 'personalizar') {
            $webToken = $context['web_menu_token'] ?? null;
            if ($webToken) {
                $tokenService = app(MenuTokenService::class);
                $url = $tokenService->buildItemUrl($webToken);

                return [
                    'pre_messages' => [
                        [
                            'type' => 'cta_url',
                            'body' => "Personaliza *{$pendingItem['name']}* con fotos y opciones visuales:",
                            'button_text' => 'Abrir menu',
                            'url' => $url,
                        ],
                    ],
                    'response' => "Toca el boton de arriba para personalizar tu producto.\n\nO si prefieres, puedes elegir aqui escribiendo el numero de tu opcion.",
                    'context_data' => $context,
                ];
            }
        }

        // Allow user to cancel at any point
        if (in_array($messageLower, ['cancelar', 'cancel', 'menu', 'volver'])) {
            return [
                'response' => "Seleccion cancelada. Volvamos al menu.",
                'next_state' => 'menu_browsing',
                'context_data' => [],
            ];
        }

        $step = $context['modifier_step'] ?? 'variant';

        return match ($step) {
            'variant' => $this->handleVariantStep($session, $message, $context),
            'optional' => $this->handleOptionalStep($session, $message, $context),
            'confirm' => $this->handleConfirmStep($session, $message, $messageType, $context),
            default => [
                'response' => 'Ocurrio un error. Volvamos al menu.',
                'next_state' => 'menu_browsing',
            ],
        };
    }

    private function handleVariantStep(ChatSession $session, string $message, array $context): array
    {
        $pendingItem = $context['pending_item'];
        $config = $pendingItem['modifiers_config'];
        $variantGroups = $config['variant_groups'] ?? [];
        $groupIndex = $context['current_variant_group_index'] ?? 0;

        if ($groupIndex >= count($variantGroups)) {
            return $this->advanceToNextStep($session, $context);
        }

        $group = $variantGroups[$groupIndex];
        $options = $group['options'] ?? [];

        // Parse user selection (number or name)
        $selected = $this->findOption($message, $options);

        if (!$selected) {
            return [
                'response' => "No entendi tu seleccion. " .
                    MessageFactory::variantGroupPrompt($pendingItem['name'], $group),
                'context_data' => $context,
            ];
        }

        // Store selection
        $selectedVariants = $context['selected_variants'] ?? [];
        $selectedVariants[$group['name']] = [
            'name' => $selected['name'],
            'price' => (float) $selected['price'],
        ];

        $context['selected_variants'] = $selectedVariants;
        $context['current_variant_group_index'] = $groupIndex + 1;

        // Check if more variant groups
        if ($context['current_variant_group_index'] < count($variantGroups)) {
            $nextGroup = $variantGroups[$context['current_variant_group_index']];
            return [
                'response' => "Perfecto, *{$selected['name']}*.\n\n" .
                    MessageFactory::variantGroupPrompt($pendingItem['name'], $nextGroup),
                'context_data' => $context,
            ];
        }

        return $this->advanceToNextStep($session, $context);
    }

    private function handleOptionalStep(ChatSession $session, string $message, array $context): array
    {
        $pendingItem = $context['pending_item'];
        $config = $pendingItem['modifiers_config'];
        $optionalGroups = $config['optional_groups'] ?? [];
        $groupIndex = $context['current_optional_group_index'] ?? 0;

        if ($groupIndex >= count($optionalGroups)) {
            return $this->showConfirmation($session, $context);
        }

        $group = $optionalGroups[$groupIndex];
        $options = $group['options'] ?? [];
        $messageLower = mb_strtolower(trim($message));

        // "no" or "ninguno" means skip this group
        if (in_array($messageLower, ['no', 'ninguno', 'nada', 'sin extras', 'ninguna'])) {
            $context['current_optional_group_index'] = $groupIndex + 1;

            if ($context['current_optional_group_index'] < count($optionalGroups)) {
                $nextGroup = $optionalGroups[$context['current_optional_group_index']];
                return [
                    'response' => "Ok, sin {$group['name']}.\n\n" .
                        MessageFactory::optionalGroupPrompt($pendingItem['name'], $nextGroup),
                    'context_data' => $context,
                ];
            }

            return $this->showConfirmation($session, $context);
        }

        // Parse comma-separated numbers or names
        $selections = $this->parseMultipleSelections($message, $options);

        if (empty($selections)) {
            return [
                'response' => "No entendi tu seleccion. Escribe los numeros separados por coma o 'no' para continuar.\n\n" .
                    MessageFactory::optionalGroupPrompt($pendingItem['name'], $group),
                'context_data' => $context,
            ];
        }

        // Validate min/max
        $min = $group['min'] ?? 0;
        $max = $group['max'] ?? count($options);
        if (count($selections) < $min) {
            return [
                'response' => "Debes seleccionar al menos {$min} opcion(es). Intenta de nuevo.\n\n" .
                    MessageFactory::optionalGroupPrompt($pendingItem['name'], $group),
                'context_data' => $context,
            ];
        }
        if (count($selections) > $max) {
            return [
                'response' => "Maximo {$max} opcion(es). Intenta de nuevo.\n\n" .
                    MessageFactory::optionalGroupPrompt($pendingItem['name'], $group),
                'context_data' => $context,
            ];
        }

        // Store selections
        $selectedOptionals = $context['selected_optionals'] ?? [];
        foreach ($selections as $sel) {
            $selectedOptionals[] = [
                'name' => $sel['name'],
                'price' => (float) $sel['price'],
                'group' => $group['name'],
            ];
        }

        $context['selected_optionals'] = $selectedOptionals;
        $context['current_optional_group_index'] = $groupIndex + 1;

        $selNames = implode(', ', array_column($selections, 'name'));

        if ($context['current_optional_group_index'] < count($optionalGroups)) {
            $nextGroup = $optionalGroups[$context['current_optional_group_index']];
            return [
                'response' => "Agregado: *{$selNames}*.\n\n" .
                    MessageFactory::optionalGroupPrompt($pendingItem['name'], $nextGroup),
                'context_data' => $context,
            ];
        }

        return $this->showConfirmation($session, $context, "Agregado: *{$selNames}*.\n\n");
    }

    private function handleConfirmStep(ChatSession $session, string $message, string $messageType, array $context): array
    {
        $messageLower = mb_strtolower(trim($message));

        // Button replies
        if ($messageType === 'button') {
            $messageLower = $message;
        }

        if (in_array($messageLower, ['mod_confirm', 'si', 'agregar', 'ok', 'dale'])) {
            return $this->addToCart($session, $context);
        }

        if (in_array($messageLower, ['mod_change', 'cambiar', 'modificar'])) {
            $context['modifier_step'] = 'variant';
            $context['current_variant_group_index'] = 0;
            $context['current_optional_group_index'] = 0;
            $context['selected_variants'] = [];
            $context['selected_optionals'] = [];

            $pendingItem = $context['pending_item'];
            $config = $pendingItem['modifiers_config'];

            if (!empty($config['variant_groups'])) {
                return [
                    'response' => "Vamos de nuevo.\n\n" .
                        MessageFactory::variantGroupPrompt($pendingItem['name'], $config['variant_groups'][0]),
                    'context_data' => $context,
                ];
            }

            if (!empty($config['optional_groups'])) {
                $context['modifier_step'] = 'optional';
                return [
                    'response' => "Vamos de nuevo.\n\n" .
                        MessageFactory::optionalGroupPrompt($pendingItem['name'], $config['optional_groups'][0]),
                    'context_data' => $context,
                ];
            }
        }

        if (in_array($messageLower, ['mod_cancel', 'no', 'cancelar'])) {
            return [
                'response' => "Seleccion cancelada. Puedes elegir otro producto.",
                'next_state' => 'item_selection',
                'context_data' => array_diff_key($context, array_flip([
                    'pending_item', 'modifier_step', 'current_variant_group_index',
                    'current_optional_group_index', 'selected_variants', 'selected_optionals',
                ])),
            ];
        }

        return $this->showConfirmation($session, $context, "No entendi. ");
    }

    private function advanceToNextStep(ChatSession $session, array $context): array
    {
        $pendingItem = $context['pending_item'];
        $config = $pendingItem['modifiers_config'];

        // Last selected variant name for feedback
        $lastVariant = '';
        $selectedVariants = $context['selected_variants'] ?? [];
        if ($selectedVariants) {
            $last = end($selectedVariants);
            $lastVariant = "Perfecto, *{$last['name']}*.\n\n";
        }

        // If optionals exist, move to optional step
        if (!empty($config['optional_groups'])) {
            $context['modifier_step'] = 'optional';
            $context['current_optional_group_index'] = 0;
            return [
                'response' => $lastVariant .
                    MessageFactory::optionalGroupPrompt($pendingItem['name'], $config['optional_groups'][0]),
                'context_data' => $context,
            ];
        }

        // No optionals, go to confirm
        return $this->showConfirmation($session, $context, $lastVariant);
    }

    private function showConfirmation(ChatSession $session, array $context, string $prefix = ''): array
    {
        $pendingItem = $context['pending_item'];
        $selectedModifiers = [
            'variants' => $context['selected_variants'] ?? [],
            'optionals' => $context['selected_optionals'] ?? [],
        ];

        $unitPrice = self::calculateUnitPrice($pendingItem, $selectedModifiers);
        $context['modifier_step'] = 'confirm';

        return [
            'response' => $prefix .
                MessageFactory::modifierConfirmText(
                    $pendingItem['name'],
                    $pendingItem['quantity'],
                    $selectedModifiers,
                    $unitPrice
                ),
            'response_type' => 'buttons',
            'buttons' => [
                ['id' => 'mod_confirm', 'title' => 'Agregar'],
                ['id' => 'mod_change', 'title' => 'Cambiar'],
                ['id' => 'mod_cancel', 'title' => 'Cancelar'],
            ],
            'context_data' => $context,
        ];
    }

    private function addToCart(ChatSession $session, array $context): array
    {
        $pendingItem = $context['pending_item'];
        $selectedModifiers = [
            'variants' => $context['selected_variants'] ?? [],
            'optionals' => $context['selected_optionals'] ?? [],
        ];

        $unitPrice = self::calculateUnitPrice($pendingItem, $selectedModifiers);
        $quantity = $pendingItem['quantity'];

        $cart = $session->cart_data ?? ['items' => [], 'subtotal' => 0, 'delivery_fee' => 0, 'total' => 0];

        // Check for duplicate (same item + same modifiers)
        $modifiersJson = json_encode($selectedModifiers);
        $found = false;
        foreach ($cart['items'] as &$cartItem) {
            if ($cartItem['menu_item_id'] == $pendingItem['menu_item_id']
                && json_encode($cartItem['modifiers'] ?? []) === $modifiersJson) {
                $cartItem['quantity'] += $quantity;
                $cartItem['subtotal'] = $cartItem['quantity'] * $cartItem['unit_price'];
                $found = true;
                break;
            }
        }
        unset($cartItem);

        if (!$found) {
            $cart['items'][] = [
                'menu_item_id' => $pendingItem['menu_item_id'],
                'name' => $pendingItem['name'],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'modifiers' => $selectedModifiers,
                'subtotal' => $quantity * $unitPrice,
            ];
        }

        // Recalculate totals
        $cart['subtotal'] = array_sum(array_column($cart['items'], 'subtotal'));
        $cart['total'] = $cart['subtotal'] + ($cart['delivery_fee'] ?? 0);

        $price = number_format($unitPrice * $quantity, 0, '.', ',');
        $modDesc = MessageFactory::modifierDescription($selectedModifiers);

        // Clean modifier context
        $cleanContext = array_diff_key($context, array_flip([
            'pending_item', 'modifier_step', 'current_variant_group_index',
            'current_optional_group_index', 'selected_variants', 'selected_optionals',
        ]));

        return [
            'response' => "Perfecto! Agregado:\n{$quantity}x {$pendingItem['name']}" .
                ($modDesc ? " ({$modDesc})" : '') .
                " - RD\${$price}\n\n" .
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
            'context_data' => array_merge($cleanContext, ['retry_count' => 0]),
        ];
    }

    public static function calculateUnitPrice(array $item, array $selectedModifiers): float
    {
        $variants = $selectedModifiers['variants'] ?? [];

        if (!empty($variants)) {
            // Variants replace the base price
            $basePrice = array_sum(array_column($variants, 'price'));
        } else {
            $basePrice = (float) ($item['base_price'] ?? $item['unit_price'] ?? $item['price'] ?? 0);
        }

        $optionalPrices = array_sum(array_column($selectedModifiers['optionals'] ?? [], 'price'));

        $priceBeforeTax = $basePrice + $optionalPrices;

        // Apply configured taxes
        $tenant = app('tenant');

        return $tenant ? $tenant->applyTax($priceBeforeTax) : $priceBeforeTax;
    }

    private function findOption(string $input, array $options): ?array
    {
        $input = trim($input);

        // Try numeric index (1-based)
        if (is_numeric($input)) {
            $index = (int) $input - 1;
            return $options[$index] ?? null;
        }

        // Try exact name match (case-insensitive)
        $inputLower = mb_strtolower($input);
        foreach ($options as $opt) {
            if (mb_strtolower($opt['name']) === $inputLower) {
                return $opt;
            }
        }

        // Try partial match
        foreach ($options as $opt) {
            if (str_contains(mb_strtolower($opt['name']), $inputLower)) {
                return $opt;
            }
        }

        // Try fuzzy match
        return MenuSearcher::fuzzyMatch($input, $options);
    }

    private function parseMultipleSelections(string $input, array $options): array
    {
        $selections = [];
        // Split by comma, space, or "y"
        $parts = preg_split('/[,\s]+|(?:\s+y\s+)/iu', trim($input));

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') continue;

            $found = $this->findOption($part, $options);
            if ($found) {
                // Avoid duplicates
                $exists = false;
                foreach ($selections as $sel) {
                    if ($sel['name'] === $found['name']) {
                        $exists = true;
                        break;
                    }
                }
                if (!$exists) {
                    $selections[] = $found;
                }
            }
        }

        return $selections;
    }
}
