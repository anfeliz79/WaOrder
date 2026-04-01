<?php

namespace App\Services\Conversation\Handlers;

use App\Models\ChatSession;
use App\Services\AI\AiIntentService;
use App\Services\Branch\BranchRouter;

class CollectingInfoHandler implements HandlerInterface
{
    /**
     * Get enabled payment methods from tenant settings.
     */
    private function getEnabledPaymentMethods(): array
    {
        $tenant = app('tenant');
        $methods = $tenant->getSetting('payment.methods', ['cash', 'transfer']);
        $customMethods = $tenant->getSetting('payment.custom_methods', []);

        $builtInLabels = [
            'cash' => 'Efectivo',
            'transfer' => 'Transferencia',
            'card_link' => 'Pago con link',
            'cardnet' => 'Tarjeta de credito/debito',
        ];

        $enabled = [];
        foreach ($methods as $method) {
            if (isset($builtInLabels[$method])) {
                $enabled[$method] = $builtInLabels[$method];
            } elseif (isset($customMethods[$method])) {
                $enabled[$method] = $customMethods[$method]['name'];
            }
        }

        return $enabled;
    }

    /**
     * Build the payment method prompt text from enabled methods.
     */
    private function buildPaymentPrompt(): string
    {
        $methods = $this->getEnabledPaymentMethods();
        $lines = ["Forma de pago:"];
        $i = 1;
        foreach ($methods as $key => $label) {
            $lines[] = "{$i}. {$label}";
            $i++;
        }

        return implode("\n", $lines);
    }

    /**
     * Parse payment method selection from user input.
     * Returns the method key (cash, transfer, card_link) or null if not recognized.
     */
    /**
     * Build interactive payment method selection (buttons or list).
     */
    private function buildPaymentInteraction(): array
    {
        $methods = $this->getEnabledPaymentMethods();

        if (count($methods) <= 3) {
            $buttons = [];
            foreach ($methods as $key => $label) {
                $buttons[] = [
                    'id' => 'pay_' . $key,
                    'title' => mb_substr($label, 0, 20),
                ];
            }
            return ['type' => 'buttons', 'buttons' => $buttons];
        }

        $rows = [];
        foreach ($methods as $key => $label) {
            $rows[] = [
                'id' => 'pay_' . $key,
                'title' => mb_substr($label, 0, 24),
            ];
        }
        return [
            'type' => 'list',
            'list_button_text' => 'Ver opciones',
            'list_sections' => [
                ['title' => 'Metodos de pago', 'rows' => $rows],
            ],
        ];
    }

    /**
     * Parse payment method selection from user input.
     * Returns the method key (cash, transfer, card_link) or null if not recognized.
     */
    private function parsePaymentSelection(string $input): ?string
    {
        $lower = mb_strtolower(trim($input));
        $methods = $this->getEnabledPaymentMethods();
        $indexed = array_values(array_keys($methods));

        // Check button/list ID replies (pay_cash, pay_transfer, pay_yappy, etc.)
        if (str_starts_with($lower, 'pay_')) {
            $key = substr($lower, 4);
            if (isset($methods[$key])) {
                return $key;
            }
        }

        // Check numeric selection
        if (is_numeric($lower)) {
            $idx = (int) $lower - 1;
            if (isset($indexed[$idx])) {
                return $indexed[$idx];
            }
        }

        // Built-in text aliases
        $aliases = [
            'cash' => ['efectivo', 'cash'],
            'transfer' => ['transferencia', 'transfer'],
            'card_link' => ['link', 'pago con link', 'card_link'],
            'cardnet' => ['tarjeta', 'cardnet', 'card', 'pago con tarjeta', 'tarjeta de credito', 'tarjeta de debito'],
        ];

        foreach ($aliases as $method => $words) {
            if (isset($methods[$method]) && in_array($lower, $words)) {
                return $method;
            }
        }

        // Match custom methods by name (case-insensitive)
        foreach ($methods as $key => $label) {
            if (mb_strtolower($label) === $lower) {
                return $key;
            }
        }

        return null;
    }

    /**
     * Build the response message after a payment method is selected.
     */
    private function buildPaymentMethodResponse(string $method): ?string
    {
        $tenant = app('tenant');

        if ($method === 'transfer') {
            $info = $tenant->getSetting('payment.transfer_info', []);
            if (!empty($info['bank'])) {
                $lines = ["Datos para transferencia:"];
                $lines[] = "Banco: {$info['bank']}";
                if (!empty($info['account_type'])) {
                    $lines[] = "Tipo: {$info['account_type']}";
                }
                if (!empty($info['account_number'])) {
                    $lines[] = "Cuenta: {$info['account_number']}";
                }
                if (!empty($info['holder_name'])) {
                    $lines[] = "Titular: {$info['holder_name']}";
                }
                if (!empty($info['rnc'])) {
                    $lines[] = "RNC/Cedula: {$info['rnc']}";
                }
                $lines[] = "\nEnvia el comprobante cuando realices la transferencia.";

                return implode("\n", $lines);
            }
        }

        if ($method === 'cardnet') {
            return "Recibiras un enlace de pago seguro para completar el pago con tu tarjeta una vez confirmado el pedido.";
        }

        if ($method === 'card_link') {
            $cardLink = $tenant->getSetting('payment.card_link', []);
            $lines = [];
            if (!empty($cardLink['instructions'])) {
                $lines[] = $cardLink['instructions'];
            }
            if (!empty($cardLink['url'])) {
                $lines[] = $cardLink['url'];
            }

            return !empty($lines) ? implode("\n", $lines) : null;
        }

        // Custom methods: show instructions if configured
        $customMethods = $tenant->getSetting('payment.custom_methods', []);
        if (isset($customMethods[$method]) && !empty($customMethods[$method]['instructions'])) {
            return $customMethods[$method]['instructions'];
        }

        return null;
    }

    /**
     * Build branch selection response. If branchesWithDistance is null, fetches all branches.
     */
    private function buildBranchSelectionResponse(array $info, array $context, ?array $branchesWithDistance): array
    {
        $tenant = app('tenant');
        $branchRouter = app(BranchRouter::class);

        if ($branchesWithDistance === null) {
            $branchesWithDistance = $branchRouter->getAllBranches($tenant->id);
        }

        if (empty($branchesWithDistance)) {
            // Fallback: no branches configured, assign default and go to payment
            $defaultBranch = $branchRouter->getDefaultBranch($tenant->id);
            $info['branch_id'] = $defaultBranch?->id;
            $interaction = $this->buildPaymentInteraction();
            return array_filter([
                'response' => '¿Cómo deseas pagar?',
                'response_type' => $interaction['type'],
                'buttons' => $interaction['buttons'] ?? null,
                'list_button_text' => $interaction['list_button_text'] ?? null,
                'list_sections' => $interaction['list_sections'] ?? null,
                'collected_info' => $info,
                'context_data' => array_merge($context, ['awaiting_field' => 'payment_method']),
            ], fn ($v) => $v !== null);
        }

        // Store branch IDs in order for later selection
        $pickupBranches = array_map(fn ($bwd) => [
            'id' => $bwd['branch']->id,
            'name' => $bwd['branch']->name,
        ], $branchesWithDistance);

        // Build display text
        $lines = ["📍 Selecciona la sucursal de tu preferencia:"];
        foreach ($branchesWithDistance as $i => $bwd) {
            $branch = $bwd['branch'];
            $distText = '';
            if ($bwd['distance'] !== null) {
                $distText = ' (' . number_format($bwd['distance'], 1) . ' km)';
            }
            $line = ($i + 1) . ". *{$branch->name}*{$distText}";
            if (!empty($branch->address)) {
                $line .= "\n   📌 {$branch->address}";
            }
            $lines[] = $line;
        }
        $responseText = implode("\n\n", $lines);

        $newContext = array_merge($context, [
            'awaiting_field' => 'pickup_branch',
            'pickup_branches' => $pickupBranches,
        ]);

        if (count($branchesWithDistance) <= 3) {
            $buttons = array_map(fn ($bwd) => [
                'id' => 'branch_' . $bwd['branch']->id,
                'title' => mb_substr($bwd['branch']->name, 0, 20),
            ], $branchesWithDistance);

            // Build map CTA post-messages for each branch
            $mapMessages = $this->buildBranchMapMessages($branchesWithDistance);

            $result = [
                'response' => $responseText,
                'response_type' => 'buttons',
                'buttons' => $buttons,
                'collected_info' => $info,
                'context_data' => $newContext,
            ];
            if (!empty($mapMessages)) {
                $result['post_messages'] = $mapMessages;
            }
            return $result;
        }

        $rows = array_map(fn ($bwd) => [
            'id' => 'branch_' . $bwd['branch']->id,
            'title' => mb_substr($bwd['branch']->name, 0, 24),
            'description' => mb_substr($bwd['branch']->address ?? '', 0, 72),
        ], $branchesWithDistance);

        // Build map CTA post-messages for each branch
        $mapMessages = $this->buildBranchMapMessages($branchesWithDistance);

        $result = [
            'response' => $responseText,
            'response_type' => 'list',
            'list_button_text' => 'Ver sucursales',
            'list_sections' => [
                ['title' => 'Sucursales disponibles', 'rows' => $rows],
            ],
            'collected_info' => $info,
            'context_data' => $newContext,
        ];
        if (!empty($mapMessages)) {
            $result['post_messages'] = $mapMessages;
        }
        return $result;
    }

    /**
     * Build CTA URL "Cómo llegar" post-messages for each branch that has an address.
     * Each branch gets its own message with a Google Maps navigation link.
     */
    private function buildBranchMapMessages(array $branchesWithDistance): array
    {
        $messages = [];
        foreach ($branchesWithDistance as $bwd) {
            $branch = $bwd['branch'];

            // Build Google Maps URL — prefer coordinates, fall back to address string
            $lat = $branch->latitude ?? null;
            $lng = $branch->longitude ?? null;

            if ($lat && $lng) {
                $mapsUrl = "https://www.google.com/maps/dir/?api=1&destination={$lat},{$lng}";
            } elseif (!empty($branch->address)) {
                $mapsUrl = "https://www.google.com/maps/dir/?api=1&destination=" . urlencode($branch->address);
            } else {
                continue; // Skip branches with no location info
            }

            $distText = ($bwd['distance'] !== null)
                ? ' — ' . number_format($bwd['distance'], 1) . ' km'
                : '';

            $messages[] = [
                'type' => 'cta_url',
                'body' => "🗺️ *{$branch->name}*{$distText}" . (!empty($branch->address) ? "\n📌 {$branch->address}" : ''),
                'button_text' => 'Cómo llegar',
                'url' => $mapsUrl,
            ];
        }
        return $messages;
    }

    public function handle(ChatSession $session, string $message, string $messageType): array
    {
        $info = $session->collected_info ?? [];
        $context = $session->context_data ?? [];
        $awaitingField = $context['awaiting_field'] ?? null;
        $message = trim($message);

        $lower = mb_strtolower($message);

        // Collect name
        if ($awaitingField === 'name' || empty($info['name'])) {
            $info['name'] = $message;

            return [
                'response' => "¡Gracias, {$message}! 😊 ¿Cómo deseas recibir tu pedido?",
                'response_type' => 'buttons',
                'buttons' => [
                    ['id' => 'info_delivery', 'title' => '🛵 Delivery'],
                    ['id' => 'info_pickup', 'title' => '🏪 Recoger'],
                ],
                'collected_info' => $info,
                'context_data' => array_merge($context, ['awaiting_field' => 'delivery_type']),
            ];
        }

        // Collect delivery type
        if ($awaitingField === 'delivery_type') {
            if (in_array($lower, ['info_delivery', '1', 'delivery', 'envio', 'domicilio', '🛵 delivery'])) {
                $info['delivery_type'] = 'delivery';

                return [
                    'response' => "¡Perfecto! 🛵 Para verificar que llegamos a tu zona, *comparte tu ubicación de WhatsApp*.\n\n📍 Toca el clip de adjuntos → *Ubicación* → *Enviar mi ubicación actual*.",
                    'response_type' => 'text',
                    'collected_info' => $info,
                    'context_data' => array_merge($context, ['awaiting_field' => 'address']),
                ];
            }

            if (in_array($lower, ['info_pickup', '2', 'pickup', 'recoger', 'recogida', '🏪 recoger'])) {
                $info['delivery_type'] = 'pickup';

                return [
                    'response' => "¡Claro! 🏪 Para recomendarte la sucursal más cercana, comparte tu ubicación de WhatsApp:",
                    'response_type' => 'buttons',
                    'buttons' => [
                        ['id' => 'info_skip_location', 'title' => 'Ver todas'],
                    ],
                    'collected_info' => $info,
                    'context_data' => array_merge($context, ['awaiting_field' => 'pickup_location']),
                ];
            }

            // AI fallback: interpret natural language delivery preference
            $ai = app(AiIntentService::class);
            $aiDelivery = $ai->interpretDeliveryType($message);

            if ($aiDelivery === 'delivery') {
                $info['delivery_type'] = 'delivery';
                return [
                    'response' => "¡Perfecto! 🛵 Para verificar que llegamos a tu zona, *comparte tu ubicación de WhatsApp*.\n\n📍 Toca el clip de adjuntos → *Ubicación* → *Enviar mi ubicación actual*.",
                    'response_type' => 'text',
                    'collected_info' => $info,
                    'context_data' => array_merge($context, ['awaiting_field' => 'address']),
                ];
            }

            if ($aiDelivery === 'pickup') {
                $info['delivery_type'] = 'pickup';
                return [
                    'response' => "¡Claro! 🏪 Para recomendarte la sucursal más cercana, comparte tu ubicación de WhatsApp:",
                    'response_type' => 'buttons',
                    'buttons' => [
                        ['id' => 'info_skip_location', 'title' => 'Ver todas'],
                    ],
                    'collected_info' => $info,
                    'context_data' => array_merge($context, ['awaiting_field' => 'pickup_location']),
                ];
            }

            $guideText = $ai->guideUser($message, 'delivery_type', 'elegir si quiere delivery a domicilio o recoger en tienda');

            return [
                'response' => $guideText ?? '¿Cómo prefieres recibir tu pedido?',
                'response_type' => 'buttons',
                'buttons' => [
                    ['id' => 'info_delivery', 'title' => '🛵 Delivery'],
                    ['id' => 'info_pickup', 'title' => '🏪 Recoger'],
                ],
            ];
        }

        // Collect address
        if ($awaitingField === 'address') {
            // Handle location message type (GPS coordinates from WhatsApp)
            if ($messageType === 'location') {
                $locationData = json_decode($message, true);
                $address = $locationData['address'] ?? $locationData['name'] ?? 'Ubicacion compartida';
                $info['address'] = $address;
                $info['latitude'] = $locationData['latitude'] ?? null;
                $info['longitude'] = $locationData['longitude'] ?? null;
            } else {
                // Customer typed text instead of sharing GPS — guide them to share location
                return [
                    'response' => "📍 Para verificar que llegamos a tu zona necesito tu ubicación exacta.\n\nToca el clip de adjuntos → *Ubicación* → *Enviar mi ubicación actual*.",
                    'response_type' => 'text',
                    'collected_info' => $info,
                    'context_data' => $context, // keep awaiting_field = 'address'
                ];
            }

            // Route to nearest branch using GPS coordinates
            $tenant = app('tenant');
            $branchRouter = app(BranchRouter::class);

            $branch = $branchRouter->findNearestBranch(
                $tenant->id,
                (float) $info['latitude'],
                (float) $info['longitude']
            );

            if (!$branch) {
                // Customer is out of delivery range
                return [
                    'response' => "Hmm, tu ubicación está fuera de nuestra área de cobertura de delivery. 😕\n\nPuedes intentar con otra dirección o pasar a recoger en una de nuestras sucursales:",
                    'response_type' => 'buttons',
                    'buttons' => [
                        ['id' => 'info_retry_address', 'title' => 'Otra dirección'],
                        ['id' => 'info_switch_pickup', 'title' => 'Recoger en tienda'],
                    ],
                    'collected_info' => $info,
                    'context_data' => array_merge($context, ['awaiting_field' => 'address_retry']),
                ];
            }

            $info['branch_id'] = $branch->id;

            // GPS validated — now ask for a text reference for the delivery driver
            return [
                'response' => "¡Perfecto, llegamos a tu zona! ✅\n\nEscribe una *referencia de tu dirección* para que el repartidor te encuentre fácil (calle, edificio, color de la casa, número de apartamento, etc.):",
                'response_type' => 'text',
                'collected_info' => $info,
                'context_data' => array_merge($context, ['awaiting_field' => 'address_reference']),
            ];
        }

        // Collect pickup location to recommend nearest branch
        if ($awaitingField === 'pickup_location') {
            $tenant = app('tenant');
            $branchRouter = app(BranchRouter::class);
            $branchesWithDistance = null;

            if ($messageType === 'location') {
                $locationData = json_decode($message, true);
                $lat = $locationData['latitude'] ?? null;
                $lng = $locationData['longitude'] ?? null;

                if ($lat && $lng) {
                    $branchesWithDistance = $branchRouter->getAllSortedByDistance($tenant->id, (float) $lat, (float) $lng);
                }
            }

            return $this->buildBranchSelectionResponse($info, $context, $branchesWithDistance);
        }

        // Handle pickup branch selection
        if ($awaitingField === 'pickup_branch') {
            $pickupBranches = $context['pickup_branches'] ?? [];

            $selectedBranchId = null;

            // Button/list ID: branch_{id}
            if (preg_match('/^branch_(\d+)$/', $lower, $matches)) {
                $selectedBranchId = (int) $matches[1];
            } elseif (is_numeric($lower)) {
                $idx = (int) $lower - 1;
                if (isset($pickupBranches[$idx])) {
                    $selectedBranchId = $pickupBranches[$idx]['id'];
                }
            } else {
                // Match by name
                foreach ($pickupBranches as $b) {
                    if (mb_strtolower($b['name']) === $lower) {
                        $selectedBranchId = $b['id'];
                        break;
                    }
                }
            }

            if (!$selectedBranchId) {
                return $this->buildBranchSelectionResponse($info, $context, null);
            }

            $selectedBranch = \App\Models\Branch::find($selectedBranchId);
            if (!$selectedBranch) {
                return $this->buildBranchSelectionResponse($info, $context, null);
            }

            $info['branch_id'] = $selectedBranch->id;
            $branchInfo = "*{$selectedBranch->name}*";
            if (!empty($selectedBranch->address)) {
                $branchInfo .= "\n📌 {$selectedBranch->address}";
            }

            $interaction = $this->buildPaymentInteraction();
            return array_filter([
                'response' => "¡Perfecto! 🏪 Te esperamos en {$branchInfo}\n\n💳 ¿Cómo deseas pagar?",
                'response_type' => $interaction['type'],
                'buttons' => $interaction['buttons'] ?? null,
                'list_button_text' => $interaction['list_button_text'] ?? null,
                'list_sections' => $interaction['list_sections'] ?? null,
                'collected_info' => $info,
                'context_data' => array_merge($context, ['awaiting_field' => 'payment_method']),
            ], fn ($v) => $v !== null);
        }

        // Handle address retry (out of delivery range)
        if ($awaitingField === 'address_retry') {
            if (in_array($lower, ['info_retry_address', '1', 'otra', 'otra direccion', 'otra dirección'])) {
                return [
                    'response' => "Toca el clip de adjuntos → *Ubicación* → *Enviar mi ubicación actual* para intentar con otra zona. 📍",
                    'response_type' => 'text',
                    'collected_info' => $info,
                    'context_data' => array_merge($context, ['awaiting_field' => 'address']),
                ];
            }

            if (in_array($lower, ['info_switch_pickup', '2', 'recoger', 'pickup', 'recoger en tienda'])) {
                $info['delivery_type'] = 'pickup';

                $tenant = app('tenant');
                $branchRouter = app(BranchRouter::class);

                // Use stored coordinates to sort branches by distance
                $branchesWithDistance = null;
                if (!empty($info['latitude']) && !empty($info['longitude'])) {
                    $branchesWithDistance = $branchRouter->getAllSortedByDistance(
                        $tenant->id,
                        (float) $info['latitude'],
                        (float) $info['longitude']
                    );
                }

                unset($info['address'], $info['latitude'], $info['longitude']);

                return $this->buildBranchSelectionResponse($info, $context, $branchesWithDistance);
            }

            // AI fallback: interpret natural language preference
            $ai = app(AiIntentService::class);
            $aiChoice = $ai->interpretAddressRetry($message);

            if ($aiChoice === 'retry') {
                return [
                    'response' => "Toca el clip de adjuntos → *Ubicación* → *Enviar mi ubicación actual* para intentar con otra zona. 📍",
                    'response_type' => 'text',
                    'collected_info' => $info,
                    'context_data' => array_merge($context, ['awaiting_field' => 'address']),
                ];
            }

            if ($aiChoice === 'pickup') {
                $info['delivery_type'] = 'pickup';
                $branchesWithDistance = null;
                if (!empty($info['latitude']) && !empty($info['longitude'])) {
                    $tenant = app('tenant');
                    $branchRouter = app(BranchRouter::class);
                    $branchesWithDistance = $branchRouter->getAllSortedByDistance(
                        $tenant->id,
                        (float) $info['latitude'],
                        (float) $info['longitude']
                    );
                }
                unset($info['address'], $info['latitude'], $info['longitude']);
                return $this->buildBranchSelectionResponse($info, $context, $branchesWithDistance);
            }

            return [
                'response' => '¿Qué prefieres hacer?',
                'response_type' => 'buttons',
                'buttons' => [
                    ['id' => 'info_retry_address', 'title' => 'Otra dirección'],
                    ['id' => 'info_switch_pickup', 'title' => 'Recoger en tienda'],
                ],
            ];
        }

        // Collect address reference (text note for the delivery driver)
        if ($awaitingField === 'address_reference') {
            $info['address'] = $message;

            $interaction = $this->buildPaymentInteraction();
            return array_filter([
                'response' => '💳 ¿Cómo deseas pagar?',
                'response_type' => $interaction['type'],
                'buttons' => $interaction['buttons'] ?? null,
                'list_button_text' => $interaction['list_button_text'] ?? null,
                'list_sections' => $interaction['list_sections'] ?? null,
                'collected_info' => $info,
                'context_data' => array_merge($context, ['awaiting_field' => 'payment_method']),
            ], fn ($v) => $v !== null);
        }

        // Collect payment method
        if ($awaitingField === 'payment_method') {
            $selectedMethod = $this->parsePaymentSelection($message);

            if (!$selectedMethod) {
                // AI fallback: interpret natural language payment preference
                $ai = app(AiIntentService::class);
                $selectedMethod = $ai->interpretPaymentMethod($message, $this->getEnabledPaymentMethods());
            }

            if (!$selectedMethod) {
                $interaction = $this->buildPaymentInteraction();
                return array_filter([
                    'response' => 'Selecciona tu forma de pago:',
                    'response_type' => $interaction['type'],
                    'buttons' => $interaction['buttons'] ?? null,
                    'list_button_text' => $interaction['list_button_text'] ?? null,
                    'list_sections' => $interaction['list_sections'] ?? null,
                ], fn ($v) => $v !== null);
            }

            $info['payment_method'] = $selectedMethod;

            // Build response with payment details if applicable
            $paymentDetails = $this->buildPaymentMethodResponse($selectedMethod);
            $notesPrompt = "¿Tienes alguna nota especial para tu pedido? (ingredientes, instrucciones de entrega, etc.)\n_Escribe 'no' si no tienes ninguna._";

            $response = $paymentDetails
                ? "{$paymentDetails}\n\n{$notesPrompt}"
                : $notesPrompt;

            return [
                'response' => $response,
                'response_type' => 'text',
                'collected_info' => $info,
                'context_data' => array_merge($context, ['awaiting_field' => 'notes']),
            ];
        }

        // Collect notes
        if ($awaitingField === 'notes') {
            $lower = mb_strtolower($message);
            $info['notes'] = in_array($lower, ['no', 'ninguna', 'nada', 'n', 'ninguno']) ? null : $message;

            // Build confirmation
            $tenant = app('tenant');
            $cart = $session->cart_data ?? ['items' => [], 'subtotal' => 0];
            $deliveryFee = ($info['delivery_type'] === 'delivery') ? (float) ($tenant->getSetting('delivery_fee', 0)) : 0;

            $taxAmount = $tenant->extractTax($cart['subtotal']);

            $orderData = [
                'items' => $cart['items'],
                'subtotal' => $cart['subtotal'],
                'tax' => $taxAmount,
                'delivery_fee' => $deliveryFee,
                'total' => $cart['subtotal'] + $deliveryFee,
                'name' => $info['name'],
                'delivery_type' => $info['delivery_type'],
                'address' => $info['address'] ?? null,
                'payment_method' => $info['payment_method'],
            ];

            $response = \App\Services\WhatsApp\MessageFactory::orderConfirmationText($orderData);

            return [
                'response' => $response,
                'response_type' => 'buttons',
                'buttons' => [
                    ['id' => 'confirm_yes', 'title' => 'Confirmar'],
                    ['id' => 'confirm_modify', 'title' => 'Modificar'],
                    ['id' => 'confirm_cancel', 'title' => 'Cancelar'],
                ],
                'collected_info' => $info,
                'next_state' => 'confirmation',
                'context_data' => array_merge($context, ['awaiting_field' => null]),
            ];
        }

        // AI-guided fallback: the user said something unexpected at an unknown step
        $ai = app(AiIntentService::class);
        $stepHint = $awaitingField
            ? "completar el campo '{$awaitingField}' del pedido"
            : 'continuar con el proceso de pedido';

        $guidedResponse = $ai->guideUser($message, $awaitingField ?? 'unknown', $stepHint);

        return [
            'response' => $guidedResponse ?? 'Lo siento, no entendí. Por favor intenta de nuevo.',
            'response_type' => 'text',
        ];
    }

}
