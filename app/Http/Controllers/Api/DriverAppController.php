<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Order;
use App\Models\Tenant;
use App\Services\Order\OrderOrchestrator;
use App\Services\WhatsApp\MessageFactory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DriverAppController extends Controller
{
    public function __construct(
        private OrderOrchestrator $orchestrator,
    ) {}

    /**
     * Link mobile app via QR token (unauthenticated).
     */
    public function link(Request $request): JsonResponse
    {
        $request->validate([
            'tenant_slug' => 'required|string',
            'driver_id' => 'required|integer',
            'token' => 'required|string',
        ]);

        $tenant = Tenant::where('slug', $request->tenant_slug)
            ->where('is_active', true)
            ->first();

        if (!$tenant) {
            return response()->json(['message' => 'Negocio no encontrado.'], 404);
        }

        $driver = Driver::withoutGlobalScopes()
            ->where('id', $request->driver_id)
            ->where('tenant_id', $tenant->id)
            ->where('is_active', true)
            ->first();

        if (!$driver) {
            return response()->json(['message' => 'Mensajero no encontrado.'], 404);
        }

        if (!$driver->linking_token || $driver->linking_token !== $request->token) {
            return response()->json(['message' => 'Token invalido.'], 422);
        }

        if ($driver->linking_token_expires_at && $driver->linking_token_expires_at->isPast()) {
            return response()->json(['message' => 'Token expirado. Solicita un nuevo QR.'], 422);
        }

        // Revoke any existing tokens
        $driver->tokens()->delete();

        // Create new Sanctum token
        $accessToken = $driver->createToken('driver-app', ['driver-app'])->plainTextToken;

        // Mark as linked and clear the one-time token
        $driver->update([
            'linking_token' => null,
            'linking_token_expires_at' => null,
            'linked_at' => now(),
            'app_linked' => true,
        ]);

        Log::info('Driver app linked', [
            'driver_id' => $driver->id,
            'tenant_id' => $tenant->id,
        ]);

        return response()->json([
            'access_token' => $accessToken,
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'phone' => $driver->phone,
                'vehicle_type' => $driver->vehicle_type,
                'vehicle_plate' => $driver->vehicle_plate,
                'is_available' => $driver->is_available,
                'completed_deliveries' => $driver->completed_deliveries,
            ],
            'tenant' => [
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'currency' => $tenant->currency,
            ],
        ]);
    }

    /**
     * Unlink mobile app (revoke all tokens).
     */
    public function unlink(Request $request): JsonResponse
    {
        $driver = $request->user('driver');

        $driver->tokens()->delete();
        $driver->update([
            'push_token' => null,
            'device_platform' => null,
            'app_linked' => false,
        ]);

        Log::info('Driver app unlinked', ['driver_id' => $driver->id]);

        return response()->json(['message' => 'App desvinculada exitosamente.']);
    }

    /**
     * Get driver profile.
     */
    public function profile(Request $request): JsonResponse
    {
        $driver = $request->user('driver');
        $tenant = app('tenant');

        return response()->json([
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'phone' => $driver->phone,
                'vehicle_type' => $driver->vehicle_type,
                'vehicle_plate' => $driver->vehicle_plate,
                'is_available' => $driver->is_available,
                'completed_deliveries' => $driver->completed_deliveries,
            ],
            'tenant' => [
                'name' => $tenant->name,
                'currency' => $tenant->currency,
            ],
        ]);
    }

    /**
     * Toggle driver availability.
     */
    public function toggleAvailability(Request $request): JsonResponse
    {
        $driver = $request->user('driver');
        $driver->update(['is_available' => !$driver->is_available]);

        return response()->json([
            'is_available' => $driver->fresh()->is_available,
        ]);
    }

    /**
     * Register/update Expo push token.
     */
    public function updatePushToken(Request $request): JsonResponse
    {
        $request->validate([
            'push_token' => 'required|string',
            'platform' => 'required|in:ios,android',
        ]);

        $driver = $request->user('driver');
        $driver->update([
            'push_token' => $request->push_token,
            'device_platform' => $request->platform,
        ]);

        return response()->json(['message' => 'Push token registrado.']);
    }

    /**
     * List active assigned orders.
     */
    public function orders(Request $request): JsonResponse
    {
        $driver = $request->user('driver');

        $orders = Order::withoutGlobalScopes()
            ->where('driver_id', $driver->id)
            ->where('tenant_id', $driver->tenant_id)
            ->whereNotIn('status', Order::TERMINAL_STATUSES)
            ->with('items:id,order_id,name,quantity,unit_price,subtotal,modifiers')
            ->latest()
            ->get();

        return response()->json([
            'orders' => $orders->map(fn ($order) => $this->formatOrder($order)),
        ]);
    }

    /**
     * Get single order detail.
     */
    public function orderDetail(Request $request, Order $order): JsonResponse
    {
        $driver = $request->user('driver');

        if ($order->driver_id !== $driver->id) {
            return response()->json(['message' => 'Este pedido no esta asignado a ti.'], 403);
        }

        $order->load('items:id,order_id,name,quantity,unit_price,subtotal,modifiers');

        return response()->json([
            'order' => $this->formatOrder($order),
        ]);
    }

    /**
     * Mark order as delivered.
     */
    public function markDelivered(Request $request, Order $order): JsonResponse
    {
        $driver = $request->user('driver');

        if ($order->driver_id !== $driver->id) {
            return response()->json(['message' => 'Este pedido no esta asignado a ti.'], 403);
        }

        if ($order->isTerminal()) {
            $status = $order->status === 'delivered' ? 'entregado' : 'cancelado';
            return response()->json(['message' => "Este pedido ya fue marcado como {$status}."], 422);
        }

        try {
            $this->orchestrator->transition($order, 'deliver', 'driver', $driver->id);

            $driver->incrementDeliveries();

            Log::info('Driver app marked order as delivered', [
                'driver_id' => $driver->id,
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ]);

            return response()->json([
                'success' => true,
                'order_number' => $order->order_number,
                'cash_reminder' => $order->payment_method === 'cash',
                'cash_amount' => $order->payment_method === 'cash' ? $order->total : null,
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => 'No se puede marcar como entregado desde el estado actual.',
            ], 422);
        } catch (\Throwable $e) {
            Log::error('Error marking order as delivered', [
                'driver_id' => $driver->id,
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
            return response()->json(['message' => 'Error al marcar el pedido como entregado.'], 500);
        }
    }

    /**
     * Order delivery history (past deliveries).
     */
    public function orderHistory(Request $request): JsonResponse
    {
        $driver = $request->user('driver');

        $orders = Order::withoutGlobalScopes()
            ->where('driver_id', $driver->id)
            ->where('tenant_id', $driver->tenant_id)
            ->where('status', Order::STATUS_DELIVERED)
            ->with('items:id,order_id,name,quantity,subtotal')
            ->latest('completed_at')
            ->paginate(20);

        return response()->json([
            'orders' => $orders->through(fn ($order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'total' => $order->total,
                'payment_method' => $order->payment_method,
                'completed_at' => $order->completed_at?->toIso8601String(),
                'items_count' => $order->items->count(),
            ]),
            'stats' => [
                'total_deliveries' => $driver->completed_deliveries,
                'today' => Order::withoutGlobalScopes()
                    ->where('driver_id', $driver->id)
                    ->where('tenant_id', $driver->tenant_id)
                    ->where('status', Order::STATUS_DELIVERED)
                    ->whereDate('completed_at', today())
                    ->count(),
            ],
        ]);
    }

    /**
     * Format order for API response.
     */
    private function formatOrder(Order $order): array
    {
        return [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'status' => $order->status,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'delivery_address' => $order->delivery_address,
            'delivery_latitude' => $order->delivery_latitude,
            'delivery_longitude' => $order->delivery_longitude,
            'delivery_type' => $order->delivery_type,
            'total' => $order->total,
            'subtotal' => $order->subtotal,
            'delivery_fee' => $order->delivery_fee,
            'payment_method' => $order->payment_method,
            'notes' => $order->notes,
            'created_at' => $order->created_at->toIso8601String(),
            'items' => $order->items->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'subtotal' => $item->subtotal,
                'modifiers' => $item->modifiers,
            ]),
        ];
    }
}
