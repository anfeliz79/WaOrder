<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Branch;
use App\Models\Order;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TenantController extends Controller
{
    public function index(Request $request)
    {
        $query = Tenant::query();

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->input('status') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->input('status') === 'inactive') {
            $query->where('is_active', false);
        }

        $tenants = $query->withCount(['users', 'orders', 'branches'])
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('SuperAdmin/Tenants/Index', [
            'tenants' => $tenants,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function create()
    {
        return Inertia::render('SuperAdmin/Tenants/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|unique:tenants,slug|alpha_dash',
            'timezone' => 'required|string',
            'currency' => 'required|string|size:3',
            'subscription_plan' => 'required|in:free,starter,pro',
            'is_active' => 'boolean',
            // Admin user for this tenant
            'admin_name' => 'required|string|max:255',
            'admin_email' => 'required|email|max:255',
            'admin_password' => 'required|string|min:8',
            // Optional branch
            'branch_name' => 'nullable|string|max:255',
            'branch_address' => 'nullable|string',
        ]);

        $tenant = Tenant::create([
            'name' => $validated['name'],
            'slug' => $validated['slug'],
            'timezone' => $validated['timezone'],
            'currency' => $validated['currency'],
            'subscription_plan' => $validated['subscription_plan'],
            'is_active' => $validated['is_active'] ?? true,
            'settings' => [
                'setup_completed' => false,
                'delivery_enabled' => true,
                'pickup_enabled' => true,
            ],
        ]);

        // Create admin user for this tenant
        User::create([
            'name' => $validated['admin_name'],
            'email' => $validated['admin_email'],
            'password' => Hash::make($validated['admin_password']),
            'role' => 'admin',
            'tenant_id' => $tenant->id,
        ]);

        // Create default branch if provided
        if (!empty($validated['branch_name'])) {
            Branch::create([
                'name' => $validated['branch_name'],
                'address' => $validated['branch_address'] ?? '',
                'tenant_id' => $tenant->id,
                'is_active' => true,
            ]);
        }

        return redirect()->route('superadmin.tenants.index')
            ->with('success', "Tenant '{$tenant->name}' creado exitosamente.");
    }

    public function edit(int $id)
    {
        $tenant = Tenant::withCount(['users', 'orders', 'branches', 'customers'])
            ->findOrFail($id);

        $users = User::where('tenant_id', $id)->get(['id', 'name', 'email', 'role', 'is_active', 'last_login_at']);
        $branches = Branch::where('tenant_id', $id)->get(['id', 'name', 'address', 'is_active']);

        // Order stats
        $orderStats = [
            'total' => Order::where('tenant_id', $id)->count(),
            'today' => Order::where('tenant_id', $id)->whereDate('created_at', today())->count(),
            'this_month' => Order::where('tenant_id', $id)->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
            'revenue_this_month' => Order::where('tenant_id', $id)
                ->whereMonth('created_at', now()->month)
                ->whereYear('created_at', now()->year)
                ->where('status', 'delivered')
                ->sum('total'),
        ];

        return Inertia::render('SuperAdmin/Tenants/Edit', [
            'tenant' => $tenant,
            'users' => $users,
            'branches' => $branches,
            'orderStats' => $orderStats,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $tenant = Tenant::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'slug' => 'required|string|max:100|alpha_dash|unique:tenants,slug,' . $id,
            'timezone' => 'required|string',
            'currency' => 'required|string|size:3',
            'subscription_plan' => 'required|in:free,starter,pro',
            'is_active' => 'boolean',
            'whatsapp_phone_number_id' => 'nullable|string|max:50',
            'whatsapp_business_account_id' => 'nullable|string|max:50',
            'whatsapp_access_token' => 'nullable|string',
        ]);

        $tenant->update($validated);

        return redirect()->route('superadmin.tenants.edit', $id)
            ->with('success', 'Tenant actualizado exitosamente.');
    }

    public function toggleActive(int $id)
    {
        $tenant = Tenant::findOrFail($id);
        $tenant->update(['is_active' => !$tenant->is_active]);

        $status = $tenant->is_active ? 'activado' : 'desactivado';
        return back()->with('success', "Tenant '{$tenant->name}' {$status}.");
    }

    public function destroy(int $id)
    {
        $tenant = Tenant::findOrFail($id);

        // Safety check: don't delete tenants with orders
        if (Order::where('tenant_id', $id)->exists()) {
            return back()->with('error', 'No se puede eliminar un tenant con pedidos. Desactívalo en su lugar.');
        }

        $name = $tenant->name;
        $tenant->delete();

        return redirect()->route('superadmin.tenants.index')
            ->with('success', "Tenant '{$name}' eliminado.");
    }
}
