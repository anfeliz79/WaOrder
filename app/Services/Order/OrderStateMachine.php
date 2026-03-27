<?php

namespace App\Services\Order;

class OrderStateMachine
{
    private const TRANSITIONS = [
        'confirmed' => [
            'prepare' => 'in_preparation',
            'cancel'  => 'cancelled',
        ],
        'in_preparation' => [
            'ready'  => 'ready',
            'cancel' => 'cancelled',
        ],
        'ready' => [
            'pickup'   => 'delivered',
            'dispatch' => 'out_for_delivery',
            'cancel'   => 'cancelled',
        ],
        'out_for_delivery' => [
            'deliver' => 'delivered',
            'cancel'  => 'cancelled',
        ],
        // Terminal states: delivered, cancelled — no transitions
    ];

    public function canTransition(string $currentStatus, string $action): bool
    {
        return isset(self::TRANSITIONS[$currentStatus][$action]);
    }

    public function getNextStatus(string $currentStatus, string $action): string
    {
        if (!$this->canTransition($currentStatus, $action)) {
            throw new \InvalidArgumentException(
                "Transicion invalida: no se puede aplicar '{$action}' desde estado '{$currentStatus}'"
            );
        }

        return self::TRANSITIONS[$currentStatus][$action];
    }

    public function getAvailableActions(string $currentStatus): array
    {
        return array_keys(self::TRANSITIONS[$currentStatus] ?? []);
    }

    public function isTerminal(string $status): bool
    {
        return in_array($status, ['delivered', 'cancelled']);
    }
}
