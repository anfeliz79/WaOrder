<?php

namespace App\Services\Notification;

use App\Models\Driver;
use App\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PushNotificationService
{
    private const EXPO_PUSH_URL = 'https://exp.host/--/api/v2/push/send';

    public function sendToDriver(Driver $driver, string $title, string $body, array $data = []): bool
    {
        if (!$driver->hasPushToken()) {
            Log::warning('Driver has no push token', ['driver_id' => $driver->id]);
            return false;
        }

        try {
            $response = Http::post(self::EXPO_PUSH_URL, [
                'to' => $driver->push_token,
                'title' => $title,
                'body' => $body,
                'data' => $data,
                'sound' => 'default',
                'priority' => 'high',
                'channelId' => 'orders',
            ]);

            $result = $response->json();

            // Handle invalid push tokens
            if (isset($result['data']['status']) && $result['data']['status'] === 'error') {
                $errorType = $result['data']['details']['error'] ?? '';
                if ($errorType === 'DeviceNotRegistered') {
                    $driver->update(['push_token' => null]);
                    Log::info('Cleared invalid push token for driver', ['driver_id' => $driver->id]);
                    return false;
                }
            }

            Log::info('Push notification sent to driver', [
                'driver_id' => $driver->id,
                'title' => $title,
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('Failed to send push notification', [
                'driver_id' => $driver->id,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function sendNewAssignment(Driver $driver, Order $order): bool
    {
        $itemCount = $order->items->count();
        $itemsText = $itemCount === 1 ? '1 producto' : "{$itemCount} productos";

        return $this->sendToDriver(
            $driver,
            "Nueva entrega: #{$order->order_number}",
            "{$order->customer_name} - {$itemsText} - \${$order->total}",
            [
                'type' => 'new_assignment',
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ],
        );
    }

    public function sendOrderUpdate(Driver $driver, Order $order, string $message): bool
    {
        return $this->sendToDriver(
            $driver,
            "Pedido #{$order->order_number}",
            $message,
            [
                'type' => 'order_update',
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ],
        );
    }
}
