<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Order;
use App\Models\Customer;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $stats = [
            'total_tenants' => Tenant::count(),
            'active_tenants' => Tenant::where('is_active', true)->count(),
            'total_users' => User::where('role', '!=', 'superadmin')->count(),
            'total_orders' => Order::count(),
            'total_customers' => Customer::count(),
            'recent_tenants' => Tenant::latest()->take(5)->get(['id', 'name', 'slug', 'is_active', 'subscription_plan', 'created_at']),
            'recent_orders' => Order::with('tenant:id,name')->latest()->take(10)->get(['id', 'tenant_id', 'status', 'total', 'created_at']),
        ];

        return Inertia::render('SuperAdmin/Dashboard', [
            'stats' => $stats,
        ]);
    }
}
