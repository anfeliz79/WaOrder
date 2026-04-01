<?php

namespace App\Services\Conversation\Handlers;

use App\Models\ChatSession;
use App\Models\Tenant;
use App\Services\AI\AiIntentService;
use App\Services\Order\OrderFactory;
use App\Services\WhatsApp\MessageFactory;

class ConfirmationHandler implements HandlerInterface
{
    public function handle(ChatSession $session, string $message, string $messageType): array
    {
        $lower = mb_strtolower(trim($message));

        // Confirm (button ID or text)
        if (in_array($lower, ['confirm_yes', '1', 'si', 'confirmar', 'si confirmar', 'si, confirmar'])) {
            $tenant = app('tenant');
            $factory = app(OrderFactory::class);

            try {
                $order = $factory->createFromSession($session, $tenant);

                return [
                    'response' => "Tu pedido ha sido confirmado!\nPedido #{$order->order_number}\nTe avisaremos cuando este en preparacion.",
                    'next_state' => 'order_active',
                    'active_order_id' => $order->id,
                    'cart_data' => ['items' => [], 'subtotal' => 0, 'delivery_fee' => 0, 'total' => 0],
                ];
            } catch (\Exception $e) {
                \Illuminate\Support\Facades\Log::error('Order creation failed', [
                    'session_id' => $session->id,
                    'error' => $e->getMessage(),
                ]);

                $errorResult = [
                    'response' => 'Lo siento, ocurrio un error al crear tu pedido. Por favor intenta de nuevo o contacta al restaurante.',
                    'response_type' => 'buttons',
                    'buttons' => [
                        ['id' => 'confirm_yes', 'title' => 'Reintentar'],
                    ],
                ];

                $tenant = app('tenant');
                $restaurantPhone = $tenant->getSetting('restaurant_phone');
                if ($restaurantPhone) {
                    $cleanPhone = preg_replace('/[^0-9+]/', '', $restaurantPhone);
                    if (!str_starts_with($cleanPhone, '+')) {
                        $cleanPhone = '+' . $cleanPhone;
                    }
                    $errorResult['post_messages'] = [[
                        'type' => 'cta_url',
                        'body' => "\u{260E}\u{FE0F} Para llamar al restaurante:",
                        'button_text' => 'Llamar restaurante',
                        'url' => "tel:{$cleanPhone}",
                    ]];
                }

                return $errorResult;
            }
        }

        // Modify (button ID or text)
        if (in_array($lower, ['confirm_modify', '2', 'modificar', 'cambiar'])) {
            $cart = $session->cart_data ?? ['items' => []];

            return [
                'response' => MessageFactory::cartSummaryText($cart['items'], $cart['subtotal'] ?? 0),
                'next_state' => 'cart_review',
            ];
        }

        // Cancel (button ID or text)
        if (in_array($lower, ['confirm_cancel', '3', 'cancelar', 'no'])) {
            // Preserve the customer name so it doesn't get re-asked on the next order within the same session
            $currentInfo = $session->collected_info ?? [];
            return [
                'response' => "Pedido cancelado. Escribe cuando quieras pedir de nuevo.",
                'next_state' => 'greeting',
                'cart_data' => ['items' => [], 'subtotal' => 0, 'delivery_fee' => 0, 'total' => 0],
                'collected_info' => [
                    'name' => $currentInfo['name'] ?? null,
                    'address' => null,
                    'delivery_type' => null,
                    'payment_method' => null,
                    'notes' => null,
                ],
            ];
        }

        // AI fallback: interpret natural language confirmation intent
        $ai = app(AiIntentService::class);
        $intent = $ai->interpretConfirmation($message);

        if ($intent === 'confirm') {
            return $this->handle($session, 'confirm_yes', $messageType);
        }
        if ($intent === 'modify') {
            return $this->handle($session, 'confirm_modify', $messageType);
        }
        if ($intent === 'cancel') {
            return $this->handle($session, 'confirm_cancel', $messageType);
        }

        return [
            'response' => 'Por favor selecciona una opcion:',
            'response_type' => 'buttons',
            'buttons' => [
                ['id' => 'confirm_yes', 'title' => 'Confirmar'],
                ['id' => 'confirm_modify', 'title' => 'Modificar'],
                ['id' => 'confirm_cancel', 'title' => 'Cancelar'],
            ],
        ];
    }
}
