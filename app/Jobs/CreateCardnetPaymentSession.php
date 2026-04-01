<?php

namespace App\Jobs;

use App\Models\Order;
use App\Models\Tenant;
use App\Services\Notification\NotificationService;
use App\Services\Payment\CardnetService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CreateCardnetPaymentSession implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(
        public int $orderId,
        public int $tenantId,
    ) {}

    public function handle(CardnetService $cardnetService, NotificationService $notificationService): void
    {
        $order = Order::find($this->orderId);
        $tenant = Tenant::find($this->tenantId);

        if (!$order || !$tenant) {
            Log::error('CreateCardnetPaymentSession: Order or Tenant not found', [
                'order_id' => $this->orderId,
                'tenant_id' => $this->tenantId,
            ]);
            return;
        }

        $session = $cardnetService->createPaymentSession($order, $tenant);

        if ($session) {
            // Send payment link via WhatsApp
            $notificationService->sendPaymentLink($order, $tenant, $session->getPaymentUrl());
        } else {
            Log::error('CreateCardnetPaymentSession: Failed to create session', [
                'order_id' => $order->id,
            ]);
        }
    }
}
