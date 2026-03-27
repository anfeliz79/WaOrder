<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $today = now()->startOfDay();
        $branch = app()->bound('branch') ? app('branch') : null;

        $ordersToday = Order::whereDate('created_at', $today);
        if ($branch) {
            $ordersToday->where('branch_id', $branch->id);
        }

        $activeOrders = Order::active();
        if ($branch) {
            $activeOrders->where('branch_id', $branch->id);
        }

        $stats = [
            'orders_today' => (clone $ordersToday)->count(),
            'revenue_today' => (float) (clone $ordersToday)
                ->whereNotIn('status', ['cancelled'])
                ->sum('total'),
            'active_orders' => $activeOrders->count(),
            'avg_time' => $this->averageFulfillmentTime($branch?->id),
        ];

        $recentQuery = Order::with('items')->latest()->take(20);
        if ($branch) {
            $recentQuery->where('branch_id', $branch->id);
        }

        $recentOrders = $recentQuery->get()->map(fn ($order) => [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'customer_name' => $order->customer_name,
            'total' => $order->total,
            'status' => $order->status,
            'items_count' => $order->items->count(),
            'created_at' => $order->created_at->format('H:i'),
        ]);

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recentOrders' => $recentOrders,
        ]);
    }

    public function liveOrders()
    {
        $branch = app()->bound('branch') ? app('branch') : null;

        $query = Order::active()->with('items')->latest();
        if ($branch) {
            $query->where('branch_id', $branch->id);
        }

        $orders = $query->get()->map(fn ($order) => [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'total' => $order->total,
            'status' => $order->status,
            'delivery_type' => $order->delivery_type,
            'items_count' => $order->items->count(),
            'created_at' => $order->created_at->toISOString(),
            'confirmed_at' => $order->confirmed_at?->toISOString(),
        ]);

        return response()->json($orders);
    }

    private function averageFulfillmentTime(?int $branchId = null): int
    {
        $query = Order::where('status', 'delivered')
            ->whereDate('created_at', now()->startOfDay())
            ->whereNotNull('confirmed_at')
            ->whereNotNull('completed_at');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $orders = $query->get(['confirmed_at', 'completed_at']);

        if ($orders->isEmpty()) {
            return 0;
        }

        $totalMinutes = $orders->sum(function ($order) {
            return $order->confirmed_at->diffInMinutes($order->completed_at);
        });

        return (int) ($totalMinutes / $orders->count());
    }
}
