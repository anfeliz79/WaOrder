<?php

namespace App\Services\Conversation\Handlers;

use App\Models\ChatSession;

class OrderClosedHandler implements HandlerInterface
{
    public function handle(ChatSession $session, string $message, string $messageType): array
    {
        return [
            'response' => 'Gracias por tu compra! Escribe cuando quieras pedir de nuevo.',
            'next_state' => 'greeting',
            'destroy_session' => true,
        ];
    }
}
