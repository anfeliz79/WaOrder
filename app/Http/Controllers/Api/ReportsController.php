<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Customer;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReportsController extends Controller
{
    public function index(Request $request)
    {
        $period = $request->get('period', '7d');

        $startDate = match ($period) {
            '30d' => now()->subDays(30),
            '90d' => now()->subDays(90),
            default => now()->subDays(7),
        };

        // Summary stats
        $orders = Order::where('created_at', '>=', $startDate);
        $totalOrders = (clone $orders)->count();
        $nonCancelledOrders = (clone $orders)->where('status', '!=', 'cancelled');
        $nonCancelledCount = (clone $nonCancelledOrders)->count();
        $totalRevenue = (clone $nonCancelledOrders)->sum('total');
        $avgOrderValue = $nonCancelledCount > 0 ? $totalRevenue / $nonCancelledCount : 0;
        $cancelledCount = (clone $orders)->where('status', 'cancelled')->count();

        // Daily breakdown for chart
        $dailyOrders = Order::where('created_at', '>=', $startDate)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(total) as revenue')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // Top items
        $topItems = OrderItem::whereHas('order', fn ($q) => $q->where('created_at', '>=', $startDate)->where('status', '!=', 'cancelled'))
            ->selectRaw('name, SUM(quantity) as total_qty, SUM(subtotal) as total_revenue')
            ->groupBy('name')
            ->orderByDesc('total_qty')
            ->limit(10)
            ->get();

        // Order by status
        $ordersByStatus = Order::where('created_at', '>=', $startDate)
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->get()
            ->pluck('count', 'status');

        // Peak hours
        $peakHours = Order::where('created_at', '>=', $startDate)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
            ->groupBy('hour')
            ->orderByDesc('count')
            ->limit(5)
            ->get();

        // New vs returning customers
        $totalCustomers = Customer::where('created_at', '>=', $startDate)->count();
        $returningCustomers = Customer::where('total_orders', '>', 1)->count();

        // Delivery type breakdown
        $deliveryBreakdown = Order::where('created_at', '>=', $startDate)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('delivery_type, COUNT(*) as count')
            ->groupBy('delivery_type')
            ->get()
            ->pluck('count', 'delivery_type');

        // Payment method breakdown
        $paymentBreakdown = Order::where('created_at', '>=', $startDate)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('payment_method, COUNT(*) as count')
            ->groupBy('payment_method')
            ->get()
            ->pluck('count', 'payment_method');

        return Inertia::render('Reports/Index', [
            'period' => $period,
            'summary' => [
                'total_orders' => $totalOrders,
                'total_revenue' => round($totalRevenue, 2),
                'avg_order_value' => round($avgOrderValue, 2),
                'cancelled_count' => $cancelledCount,
                'cancel_rate' => $totalOrders > 0 ? round(($cancelledCount / $totalOrders) * 100, 1) : 0,
            ],
            'daily_orders' => $dailyOrders,
            'top_items' => $topItems,
            'orders_by_status' => $ordersByStatus,
            'peak_hours' => $peakHours,
            'customer_stats' => [
                'total' => $totalCustomers,
                'returning' => $returningCustomers,
            ],
            'delivery_breakdown' => $deliveryBreakdown,
            'payment_breakdown' => $paymentBreakdown,
        ]);
    }
}
