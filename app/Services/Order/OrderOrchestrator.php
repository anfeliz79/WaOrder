<?php

namespace App\Services\Order;

use App\Models\Order;
use App\Models\OrderStatusHistory;
use App\Services\Notification\NotificationService;
use Illuminate\Support\Facades\Log;

class OrderOrchestrator
{
    public function __construct(
        private OrderStateMachine $stateMachine,
        private NotificationService $notificationService,
    ) {}

    public function transition(
        Order $order,
        string $action,
        string $changedByType = 'system',
        ?int $changedById = null,
        ?string $note = null,
    ): Order {
        $fromStatus = $order->status;
        $toStatus = $this->stateMachine->getNextStatus($fromStatus, $action);

        $order->update([
            'status' => $toStatus,
            'completed_at' => $this->stateMachine->isTerminal($toStatus) ? now() : $order->completed_at,
            'cancelled_at' => $toStatus === 'cancelled' ? now() : $order->cancelled_at,
            'cancellation_reason' => $toStatus === 'cancelled' ? $note : $order->cancellation_reason,
        ]);

        // Log status change
        OrderStatusHistory::create([
            'order_id' => $order->id,
            'from_status' => $fromStatus,
            'to_status' => $toStatus,
            'changed_by_type' => $changedByType,
            'changed_by_id' => $changedById,
            'note' => $note,
        ]);

        Log::info('Order status changed', [
            'order_id' => $order->id,
            'order_number' => $order->order_number,
            'from' => $fromStatus,
            'to' => $toStatus,
            'by' => $changedByType,
        ]);

        // Send notification to customer
        $this->notificationService->notifyCustomerStatusChange($order, $toStatus);

        return $order->fresh();
    }

    public function getAvailableActions(Order $order): array
    {
        return $this->stateMachine->getAvailableActions($order->status);
    }
}
