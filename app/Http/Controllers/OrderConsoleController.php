<?php

namespace App\Http\Controllers;

use App\Models\Driver;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OrderConsoleController extends Controller
{
    public function index(Request $request)
    {
        $branch = app()->bound('branch') ? app('branch') : null;

        $baseQuery = Order::query();
        if ($branch) {
            $baseQuery->where('branch_id', $branch->id);
        }

        // Active orders (non-terminal) — load all for realtime Kanban
        $activeOrders = (clone $baseQuery)
            ->with(['items', 'driver:id,name,phone,vehicle_type'])
            ->whereNotIn('status', ['delivered', 'cancelled'])
            ->latest()
            ->get();

        // Recent completed (today's delivered + cancelled, last 30)
        $completedOrders = (clone $baseQuery)
            ->with(['items', 'driver:id,name,phone,vehicle_type'])
            ->whereIn('status', ['delivered', 'cancelled'])
            ->whereDate('created_at', now()->toDateString())
            ->latest()
            ->limit(30)
            ->get();

        // Status counts for badges
        $statusCounts = (clone $baseQuery)
            ->whereDate('created_at', now()->toDateString())
            ->select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->pluck('count', 'status')
            ->toArray();

        // Available drivers for assignment
        $driversQuery = Driver::active();
        if ($branch) {
            $driversQuery->where('branch_id', $branch->id);
        }

        return Inertia::render('Console/Index', [
            'activeOrders' => $activeOrders,
            'completedOrders' => $completedOrders,
            'statusCounts' => $statusCounts,
            'drivers' => $driversQuery->get(['id', 'name', 'phone', 'vehicle_type']),
        ]);
    }
}
