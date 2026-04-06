<?php

namespace App\Services\Conversation;

use App\Models\ChatSession;
use App\Models\Customer;
use App\Services\Conversation\Handlers\CartReviewHandler;
use App\Services\Conversation\Handlers\CollectingInfoHandler;
use App\Services\Conversation\Handlers\ConfirmationHandler;
use App\Services\Conversation\Handlers\GreetingHandler;
use App\Services\Conversation\Handlers\ItemSelectionHandler;
use App\Services\Conversation\Handlers\MenuBrowsingHandler;
use App\Services\Conversation\Handlers\ModifierSelectionHandler;
use App\Services\Conversation\Handlers\OrderActiveHandler;
use App\Services\Conversation\Handlers\OrderClosedHandler;
use App\Services\Conversation\Handlers\SurveyHandler;
use App\Services\Session\SessionManager;
use Illuminate\Support\Facades\Log;

class ConversationEngine
{
    private array $handlers;

    public function __construct(
        private SessionManager $sessionManager,
    ) {
        $this->handlers = [
            'greeting' => new GreetingHandler(),
            'menu_browsing' => new MenuBrowsingHandler(),
            'item_selection' => new ItemSelectionHandler(),
            'modifier_selection' => new ModifierSelectionHandler(),
            'cart_review' => new CartReviewHandler(),
            'collecting_info' => new CollectingInfoHandler(),
            'confirmation' => new ConfirmationHandler(),
            'order_active' => new OrderActiveHandler(),
            'order_closed' => new OrderClosedHandler(),
            'survey' => new SurveyHandler(),
        ];
    }

    public function process(ChatSession $session, string $message, string $messageType = 'text'): array
    {
        // Check if customer is blocked before any processing
        $blockedResponse = $this->checkBlockedCustomer($session);
        if ($blockedResponse) {
            return $blockedResponse;
        }

        // Check if bot is manually paused
        $pausedResponse = $this->checkBotPaused();
        if ($pausedResponse) {
            return $pausedResponse;
        }

        // Check business hours before processing
        $closedResponse = $this->checkBusinessHours($session);
        if ($closedResponse) {
            return $closedResponse;
        }

        $state = $session->conversation_state;

        // Auto-reset session when order has reached terminal state (cancelled/delivered)
        // This ensures the customer gets a fresh greeting immediately instead of
        // seeing the "ha sido cancelado/entregado" message first
        if ($state === 'order_closed' || ($state === 'order_active' && $session->active_order_id)) {
            $shouldReset = $state === 'order_closed';

            if (!$shouldReset && $session->active_order_id) {
                $order = \App\Models\Order::find($session->active_order_id);
                $shouldReset = $order && $order->isTerminal();
            }

            if ($shouldReset) {
                $this->sessionManager->destroy($session);
                $session = $this->sessionManager->create($session->tenant_id, $session->customer_phone);
                $state = 'greeting';
            }
        }

        $msgLower = mb_strtolower(trim($message));

        // Global greeting reset: si el usuario saluda desde cualquier estado intermedio
        // (cart_review, collecting_info, etc.), destruir la sesión y empezar de cero.
        // NO aplica en order_active (tiene pedido en curso) ni en survey (esperando rating).
        $greetingKeywords = ['hola', 'hi', 'hello', 'buenas', 'hey', 'ola', 'inicio', 'start', 'comenzar', 'reiniciar'];
        $isGreeting = in_array($msgLower, $greetingKeywords)
            || (bool) preg_match('/^(hola|buenas|buenos)\b/iu', $msgLower);

        if (
            $isGreeting
            && !in_array($state, ['greeting', 'order_active', 'survey'])
        ) {
            $this->sessionManager->destroy($session);
            $session = $this->sessionManager->create($session->tenant_id, $session->customer_phone);
            $state = 'greeting';
        }

        // Global cancel: si el usuario quiere cancelar desde cualquier estado intermedio
        $cancelKeywords = ['cancelar todo', 'cancelar pedido', 'cerrar sesion', 'salir', 'empezar de nuevo'];
        if (
            in_array($state, ['cart_review', 'menu_browsing', 'item_selection', 'modifier_selection', 'collecting_info', 'confirmation'])
            && in_array($msgLower, $cancelKeywords)
        ) {
            $this->sessionManager->destroy($session);
            return [
                'response' => "Tu pedido fue cancelado. 👍 Escríbenos cuando quieras pedir de nuevo.",
                'response_type' => 'text',
                'buttons' => null,
                'list_button_text' => null,
                'list_sections' => null,
                'cta_body' => null,
                'cta_button_text' => null,
                'cta_url' => null,
                'pre_messages' => null,
                'post_messages' => null,
                'ai_used' => false,
                'ai_model' => null,
                'ai_tokens' => null,
            ];
        }

        $handler = $this->handlers[$state] ?? $this->handlers['greeting'];

        Log::info('Conversation processing', [
            'session_id' => $session->id,
            'state' => $state,
            'message' => substr($message, 0, 100),
        ]);

        try {
            $result = $handler->handle($session, $message, $messageType);

            // Update session state
            $updateData = [];
            if (isset($result['next_state'])) {
                $updateData['conversation_state'] = $result['next_state'];
            }
            if (isset($result['cart_data'])) {
                $updateData['cart_data'] = $result['cart_data'];
            }
            if (isset($result['collected_info'])) {
                $updateData['collected_info'] = $result['collected_info'];
            }
            if (isset($result['context_data'])) {
                $updateData['context_data'] = $result['context_data'];
            }
            if (isset($result['active_order_id'])) {
                $updateData['active_order_id'] = $result['active_order_id'];
            }

            if (!empty($updateData)) {
                $this->sessionManager->update($session, $updateData);
            }

            // If session should be destroyed
            if (!empty($result['destroy_session'])) {
                $this->sessionManager->destroy($session);
            }

            return [
                'response' => $result['response'] ?? null,
                'response_type' => $result['response_type'] ?? 'text',
                'buttons' => $result['buttons'] ?? null,
                'list_button_text' => $result['list_button_text'] ?? null,
                'list_sections' => $result['list_sections'] ?? null,
                'cta_body' => $result['cta_body'] ?? null,
                'cta_button_text' => $result['cta_button_text'] ?? null,
                'cta_url' => $result['cta_url'] ?? null,
                'pre_messages' => $result['pre_messages'] ?? null,
                'post_messages' => $result['post_messages'] ?? null,
                'ai_used' => $result['ai_used'] ?? false,
                'ai_model' => $result['ai_model'] ?? null,
                'ai_tokens' => $result['ai_tokens'] ?? null,
            ];
        } catch (\Exception $e) {
            Log::error('Conversation engine error', [
                'session_id' => $session->id,
                'state' => $state,
                'error' => $e->getMessage(),
            ]);

            // If no active order, reset session so the user can start fresh
            $hasActiveOrder = $state === 'order_active';

            $errorResult = [
                'response' => 'Lo siento, ocurrio un error. Por favor intenta de nuevo.',
                'response_type' => 'text',
                'ai_used' => false,
            ];

            if (!$hasActiveOrder) {
                $this->sessionManager->destroy($session);
            }

            $tenant = app('tenant');
            if ($tenant) {
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
            }

            return $errorResult;
        }
    }

    private function checkBlockedCustomer(ChatSession $session): ?array
    {
        $customer = $session->customer
            ?? Customer::where('tenant_id', $session->tenant_id)
                ->where('phone', $session->customer_phone)
                ->first();

        if ($customer && $customer->is_blocked) {
            Log::info('Blocked customer attempted contact', [
                'customer_id' => $customer->id,
                'phone' => $session->customer_phone,
                'tenant_id' => $session->tenant_id,
            ]);

            return [
                'response' => "Lo sentimos, tu cuenta ha sido suspendida. Si crees que esto es un error, contacta al restaurante directamente.",
                'response_type' => 'text',
                'ai_used' => false,
            ];
        }

        return null;
    }

    private function checkBotPaused(): ?array
    {
        $tenant = app('tenant');
        if (!$tenant) return null;

        $isPaused = (bool) ($tenant->settings['bot_paused'] ?? false);
        if (!$isPaused) return null;

        $restaurantName = $tenant->name ?? 'el restaurante';
        $customMessage = $tenant->getSetting('bot_paused_message');

        $message = $customMessage
            ?: "Hola! Gracias por escribir a {$restaurantName}.\n\nEn este momento no estamos recibiendo pedidos. Vuelve pronto!";

        return [
            'response' => $message,
            'response_type' => 'text',
            'ai_used' => false,
        ];
    }

    private function checkBusinessHours(ChatSession $session): ?array
    {
        $tenant = app('tenant');
        if (!$tenant) return null;

        $hours = $tenant->getSetting('business_hours', []);
        if (empty($hours['enabled'])) return null;

        $tz = $tenant->timezone ?? 'America/Santo_Domingo';
        $now = now($tz);
        $currentDay = (int) $now->format('w'); // 0=Sunday, 6=Saturday
        $currentTime = $now->format('H:i');

        $openDays = $hours['days'] ?? [1, 2, 3, 4, 5, 6]; // default Mon-Sat
        $openTime = $hours['open'] ?? '08:00';
        $closeTime = $hours['close'] ?? '22:00';

        $isClosed = !in_array($currentDay, $openDays) || $currentTime < $openTime || $currentTime >= $closeTime;

        if (!$isClosed) return null;

        // Allow survey responses even outside business hours
        if ($session->conversation_state === 'survey') return null;

        // Allow checking active order status outside hours
        if ($session->conversation_state === 'order_active') return null;

        $customMessage = $hours['closed_message'] ?? null;
        $restaurantName = $tenant->name ?? 'el restaurante';

        $dayNames = ['domingo', 'lunes', 'martes', 'miercoles', 'jueves', 'viernes', 'sabado'];
        $nextOpenDay = null;
        for ($i = 1; $i <= 7; $i++) {
            $checkDay = ($currentDay + $i) % 7;
            if (in_array($checkDay, $openDays)) {
                $nextOpenDay = $dayNames[$checkDay];
                break;
            }
        }

        if ($customMessage) {
            $message = $customMessage;
        } else {
            $message = "Hola! Gracias por escribir a {$restaurantName}.\n\n"
                . "Nuestro horario de pedidos es de {$openTime} a {$closeTime}.";

            if (!in_array($currentDay, $openDays) && $nextOpenDay) {
                $message .= "\n\nEl proximo dia disponible es {$nextOpenDay}.";
            } else {
                $message .= "\n\nEstaremos disponibles a partir de las {$openTime}. Te esperamos!";
            }
        }

        return [
            'response' => $message,
            'response_type' => 'text',
            'ai_used' => false,
        ];
    }
}
