<?php

namespace App\Services\Notification;

use App\Jobs\SendWhatsAppNotification;
use App\Models\Driver;
use App\Models\Order;
use App\Models\Tenant;
use App\Services\WhatsApp\MessageFactory;
use Illuminate\Support\Facades\Log;

class DriverNotifier
{
    public function __construct(
        private PushNotificationService $pushService,
    ) {}

    public function notifyDriverAssigned(Order $order, Driver $driver): void
    {
        $order->loadMissing('items');

        $tenant = Tenant::find($order->tenant_id);
        $driverMode = $tenant?->getSetting('driver_mode', 'whatsapp');

        if ($driverMode === 'app') {
            // App mode: only send push notification, no WhatsApp
            if ($driver->app_linked && $driver->hasPushToken()) {
                $this->pushService->sendNewAssignment($driver, $order);

                Log::info('Driver notified via push notification (app mode)', [
                    'driver_id' => $driver->id,
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                ]);
            } else {
                Log::warning('Driver not reachable: app mode but driver has no push token', [
                    'driver_id' => $driver->id,
                    'order_id' => $order->id,
                ]);
            }
        } elseif ($driver->app_linked && $driver->hasPushToken()) {
            // WhatsApp mode but driver has app linked: prefer push
            $this->pushService->sendNewAssignment($driver, $order);

            Log::info('Driver notified via push notification', [
                'driver_id' => $driver->id,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);
        } else {
            // WhatsApp mode: send via WhatsApp
            $body = MessageFactory::driverAssignmentBody($order);
            $buttons = MessageFactory::driverAssignmentButtons($order->id);

            SendWhatsAppNotification::dispatch(
                $order->tenant_id,
                $driver->phone,
                $body,
                $buttons,
            );

            Log::info('Driver notified via WhatsApp', [
                'driver_id' => $driver->id,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);
        }
    }
}
