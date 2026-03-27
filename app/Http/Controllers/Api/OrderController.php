<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use App\Models\Order;
use App\Services\Notification\DriverNotifier;
use App\Services\Order\OrderOrchestrator;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OrderController extends Controller
{
    public function __construct(
        private OrderOrchestrator $orchestrator,
        private DriverNotifier $driverNotifier,
    ) {}

    public function index(Request $request)
    {
        $date = $request->input('date', now()->toDateString());
        $branch = app()->bound('branch') ? app('branch') : null;

        $query = Order::with(['items', 'driver:id,name,phone'])->latest();

        if ($branch) {
            $query->where('branch_id', $branch->id);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $query->whereDate('created_at', $date);

        $orders = $query->paginate(25)->withQueryString();

        // KPI stats for the selected date (scoped by branch)
        $dateOrders = Order::whereDate('created_at', $date);
        if ($branch) {
            $dateOrders->where('branch_id', $branch->id);
        }

        $stats = [
            'total' => (clone $dateOrders)->count(),
            'revenue' => (float) (clone $dateOrders)->whereNotIn('status', ['cancelled'])->sum('total'),
            'delivered' => (clone $dateOrders)->where('status', 'delivered')->count(),
            'cancelled' => (clone $dateOrders)->where('status', 'cancelled')->count(),
            'avg_time' => $this->averageFulfillmentTime($date, $branch?->id),
        ];

        // Status counts for badges (scoped by branch)
        $statusQuery = Order::whereDate('created_at', $date);
        if ($branch) {
            $statusQuery->where('branch_id', $branch->id);
        }
        $statusCounts = $statusQuery
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Drivers for this branch
        $driversQuery = Driver::active();
        if ($branch) {
            $driversQuery->where('branch_id', $branch->id);
        }

        if ($request->wantsJson()) {
            return response()->json(compact('orders', 'stats', 'statusCounts'));
        }

        return Inertia::render('Orders/Index', [
            'orders' => $orders,
            'stats' => $stats,
            'statusCounts' => $statusCounts,
            'filters' => $request->only(['status', 'date']),
            'drivers' => $driversQuery->get(['id', 'name', 'phone', 'vehicle_type']),
        ]);
    }

    public function show(Order $order)
    {
        $order->load(['items', 'customer', 'driver:id,name,phone,vehicle_type', 'statusHistory', 'payments']);

        return Inertia::render('Orders/Show', [
            'order' => $order,
        ]);
    }

    public function updateStatus(Request $request, Order $order)
    {
        $rules = [
            'action' => 'required|string|in:prepare,ready,dispatch,deliver,pickup,cancel',
            'note' => 'nullable|string|max:500',
        ];

        // Require cancellation reason
        if ($request->input('action') === 'cancel') {
            $rules['cancellation_reason'] = 'required|string|max:500';
        }

        $request->validate($rules);

        try {
            $note = $request->note;

            // Store cancellation reason on the order
            if ($request->action === 'cancel' && $request->cancellation_reason) {
                $order->update(['cancellation_reason' => $request->cancellation_reason]);
                $note = $request->cancellation_reason;
            }

            $this->orchestrator->transition(
                $order,
                $request->action,
                'staff',
                $request->user()?->id,
                $note,
            );

            if ($request->wantsJson()) {
                return response()->json(['success' => true, 'status' => $order->fresh()->status]);
            }

            return back()->with('success', 'Estado actualizado');
        } catch (\InvalidArgumentException $e) {
            if ($request->wantsJson()) {
                return response()->json(['error' => $e->getMessage()], 422);
            }

            return back()->withErrors(['action' => $e->getMessage()]);
        }
    }

    public function assignDriver(Request $request, Order $order)
    {
        $request->validate([
            'driver_id' => 'required|exists:drivers,id',
        ]);

        $driver = Driver::findOrFail($request->driver_id);

        $order->update(['driver_id' => $driver->id]);

        // Auto-dispatch if order is ready
        if ($order->status === Order::STATUS_READY) {
            try {
                $this->orchestrator->transition(
                    $order,
                    'dispatch',
                    'staff',
                    $request->user()?->id,
                    'Auto-dispatch al asignar mensajero',
                );
            } catch (\InvalidArgumentException $e) {
                // Non-critical: order stays in current status
            }
        }

        // Notify driver (WhatsApp or push based on tenant settings)
        $order->load('items');
        $this->driverNotifier->notifyDriverAssigned($order, $driver);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', "Orden asignada a {$driver->name}");
    }

    public function updateDeliveryAddress(Request $request, Order $order)
    {
        $request->validate([
            'delivery_address' => 'nullable|string|max:500',
        ]);

        $order->update(['delivery_address' => $request->delivery_address]);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', 'Direccion actualizada');
    }

    public function sendToDriver(Request $request, Order $order)
    {
        if (!$order->driver_id) {
            $error = 'No hay mensajero asignado a esta orden.';
            if ($request->wantsJson()) {
                return response()->json(['error' => $error], 422);
            }
            return back()->withErrors(['driver' => $error]);
        }

        $driver = Driver::findOrFail($order->driver_id);
        $order->loadMissing('items');

        $this->driverNotifier->notifyDriverAssigned($order, $driver);

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return back()->with('success', "Orden enviada a {$driver->name}");
    }

    public function latestId()
    {
        $branch = app()->bound('branch') ? app('branch') : null;
        $query = Order::query();
        if ($branch) {
            $query->where('branch_id', $branch->id);
        }

        return response()->json([
            'latest_id' => $query->max('id') ?? 0,
        ]);
    }

    public function history(Order $order)
    {
        return response()->json(
            $order->statusHistory()->latest('created_at')->get()
        );
    }

    private function averageFulfillmentTime(string $date, ?int $branchId = null): int
    {
        $query = Order::where('status', 'delivered')
            ->whereDate('created_at', $date)
            ->whereNotNull('confirmed_at')
            ->whereNotNull('completed_at');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $orders = $query->get(['confirmed_at', 'completed_at']);

        if ($orders->isEmpty()) return 0;

        $totalMinutes = $orders->sum(fn ($o) => $o->confirmed_at->diffInMinutes($o->completed_at));

        return (int) ($totalMinutes / $orders->count());
    }
}
