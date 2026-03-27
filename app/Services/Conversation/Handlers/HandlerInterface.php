<?php

namespace App\Services\Conversation\Handlers;

use App\Models\ChatSession;

interface HandlerInterface
{
    /**
     * Handle an incoming message for the current conversation state.
     *
     * Returns array with:
     * - response: string message to send back (body text)
     * - response_type: (optional) 'text', 'buttons', or 'list' (default: 'text')
     * - buttons: (optional) array of ['id' => string, 'title' => string] for interactive buttons (max 3)
     * - list_button_text: (optional) button label for interactive list
     * - list_sections: (optional) sections array for interactive list
     * - pre_messages: (optional) array of messages to send before the main response
     *     Each: ['type' => 'image'|'cta_url'|'text', ...] with type-specific fields
     * - next_state: (optional) new conversation state
     * - cart_data: (optional) updated cart
     * - collected_info: (optional) updated collected info
     * - context_data: (optional) updated context
     * - active_order_id: (optional) set active order
     * - destroy_session: (optional) bool to destroy session
     * - ai_used: (optional) bool
     */
    public function handle(ChatSession $session, string $message, string $messageType): array;
}
