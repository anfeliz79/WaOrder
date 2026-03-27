<?php

namespace App\Services\Driver;

use App\Jobs\SendWhatsAppNotification;
use App\Models\Driver;
use App\Models\Order;
use App\Models\Tenant;
use App\Services\Order\OrderOrchestrator;
use App\Services\WhatsApp\MessageFactory;
use Illuminate\Support\Facades\Log;

class DriverMessageHandler
{
    public function __construct(
        private OrderOrchestrator $orchestrator,
    ) {}

    public function handle(Tenant $tenant, Driver $driver, string $buttonId): void
    {
        $parsed = $this->parseButtonId($buttonId);

        if (!$parsed) {
            $this->sendToDriver($tenant->id, $driver->phone, 'No se pudo procesar tu solicitud. Intenta de nuevo.');
            return;
        }

        ['action' => $action, 'order_id' => $orderId] = $parsed;

        $order = Order::where('id', $orderId)
            ->where('tenant_id', $tenant->id)
            ->first();

        if (!$order) {
            $this->sendToDriver($tenant->id, $driver->phone, 'Pedido no encontrado.');
            return;
        }

        if ($order->driver_id !== $driver->id) {
            $this->sendToDriver($tenant->id, $driver->phone, 'Este pedido no esta asignado a ti.');
            return;
        }

        match ($action) {
            'delivered' => $this->handleDelivered($tenant, $driver, $order),
            'call' => $this->handleCall($tenant, $driver, $order),
            'map' => $this->handleMap($tenant, $driver, $order),
            default => $this->sendToDriver($tenant->id, $driver->phone, 'Accion no reconocida.'),
        };
    }

    private function handleDelivered(Tenant $tenant, Driver $driver, Order $order): void
    {
        if ($order->isTerminal()) {
            $status = $order->status === 'delivered' ? 'entregado' : 'cancelado';
            $this->sendToDriver($tenant->id, $driver->phone, "Este pedido ya fue marcado como {$status}.");
            return;
        }

        try {
            $this->orchestrator->transition($order, 'deliver', 'driver', $driver->id);

            $driver->incrementDeliveries();

            $this->sendToDriver(
                $tenant->id,
                $driver->phone,
                MessageFactory::driverDeliveryConfirmation($order->order_number),
            );

            if ($order->payment_method === 'cash') {
                $this->sendToDriver(
                    $tenant->id,
                    $driver->phone,
                    MessageFactory::driverCashReminder($order->order_number, $order->total),
                );
            }

            Log::info('Driver marked order as delivered', [
                'driver_id' => $driver->id,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->sendToDriver(
                $tenant->id,
                $driver->phone,
                "No se puede marcar como entregado desde el estado actual. Contacta al negocio.",
            );

            Log::warning('Driver delivery transition failed', [
                'driver_id' => $driver->id,
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function handleCall(Tenant $tenant, Driver $driver, Order $order): void
    {
        $this->sendToDriver(
            $tenant->id,
            $driver->phone,
            MessageFactory::driverCustomerContact($order->customer_name, $order->customer_phone),
        );
    }

    private function handleMap(Tenant $tenant, Driver $driver, Order $order): void
    {
        $address = $order->delivery_address;

        if (empty($address)) {
            $this->sendToDriver($tenant->id, $driver->phone, 'Este pedido no tiene direccion de entrega.');
            return;
        }

        $this->sendToDriver(
            $tenant->id,
            $driver->phone,
            MessageFactory::driverMapLink($address, $order->delivery_latitude, $order->delivery_longitude),
        );
    }

    private function parseButtonId(string $buttonId): ?array
    {
        if (!str_starts_with($buttonId, 'drv_')) {
            return null;
        }

        // Format: drv_{action}_{orderId}
        $parts = explode('_', $buttonId, 3);

        if (count($parts) !== 3 || !is_numeric($parts[2])) {
            return null;
        }

        return [
            'action' => $parts[1],
            'order_id' => (int) $parts[2],
        ];
    }

    private function sendToDriver(int $tenantId, string $phone, string $message): void
    {
        SendWhatsAppNotification::dispatch($tenantId, $phone, $message);
    }
}
