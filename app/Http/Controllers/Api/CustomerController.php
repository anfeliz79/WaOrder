<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::withCount('orders')->latest('last_order_at');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->filled('blocked')) {
            $query->where('is_blocked', true);
        }

        $customers = $query->paginate(25);

        if ($request->wantsJson()) {
            return response()->json($customers);
        }

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => $request->only('search', 'blocked'),
        ]);
    }

    public function toggleBlock(Request $request, Customer $customer)
    {
        $data = $request->validate([
            'blocked_reason' => 'nullable|string|max:255',
        ]);

        $wasBlocked = $customer->is_blocked;

        $customer->update([
            'is_blocked' => !$wasBlocked,
            'blocked_reason' => $wasBlocked ? null : ($data['blocked_reason'] ?? null),
        ]);

        $action = $customer->is_blocked ? 'bloqueado' : 'desbloqueado';

        return back()->with('success', "Cliente {$action} exitosamente.");
    }

    public function show(Request $request, Customer $customer)
    {
        // Paginated orders with items
        $orders = $customer->orders()
            ->with(['items', 'driver:id,name'])
            ->withCount('items')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        // Survey responses with order info
        $surveyResponses = $customer->surveyResponses()
            ->with('order:id,order_number,created_at')
            ->where('completed', true)
            ->latest()
            ->get();

        // Chat sessions (last 50)
        $chatSessions = $customer->chatSessions()
            ->latest()
            ->take(50)
            ->get(['id', 'customer_id', 'status', 'conversation_state', 'cart_data', 'collected_info', 'created_at', 'updated_at']);

        // Aggregate stats
        $avgOrderValue = $customer->total_orders > 0
            ? round($customer->total_spent / $customer->total_orders, 2)
            : 0;

        $avgRating = $customer->surveyResponses()
            ->where('completed', true)
            ->avg('rating');

        // Favorite items (top 5)
        $favoriteItems = OrderItem::select('name', DB::raw('SUM(quantity) as total_quantity'), DB::raw('COUNT(*) as order_count'))
            ->whereIn('order_id', $customer->orders()->select('id'))
            ->groupBy('name')
            ->orderByDesc('total_quantity')
            ->take(5)
            ->get();

        // Delivery vs pickup ratio
        $deliveryCount = $customer->orders()->where('delivery_type', 'delivery')->count();
        $pickupCount = $customer->orders()->where('delivery_type', 'pickup')->count();

        // Order frequency (orders per month since first order)
        $firstOrder = $customer->orders()->oldest()->first();
        $orderFrequency = 0;
        if ($firstOrder && $customer->total_orders > 0) {
            $monthsSinceFirst = max(1, $firstOrder->created_at->diffInMonths(now()));
            $orderFrequency = round($customer->total_orders / $monthsSinceFirst, 1);
        }

        $stats = [
            'total_orders' => $customer->total_orders,
            'total_spent' => $customer->total_spent,
            'avg_order_value' => $avgOrderValue,
            'avg_rating' => $avgRating ? round($avgRating, 1) : null,
            'favorite_items' => $favoriteItems,
            'delivery_count' => $deliveryCount,
            'pickup_count' => $pickupCount,
            'order_frequency' => $orderFrequency,
            'first_order_at' => $firstOrder?->created_at,
        ];

        if ($request->wantsJson()) {
            return response()->json(compact('customer', 'orders', 'surveyResponses', 'chatSessions', 'stats'));
        }

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
            'orders' => $orders,
            'surveyResponses' => $surveyResponses,
            'chatSessions' => $chatSessions,
            'stats' => $stats,
        ]);
    }
}
