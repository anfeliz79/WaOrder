<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class PlanController extends Controller
{
    public function index()
    {
        $plans = Plan::orderBy('sort_order')
            ->withCount('subscriptions')
            ->get();

        return Inertia::render('SuperAdmin/Plans/Index', [
            'plans' => $plans,
        ]);
    }

    public function create()
    {
        return Inertia::render('SuperAdmin/Plans/Create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->validationRules());

        $validated['slug'] = \Illuminate\Support\Str::slug($validated['name']);

        // Ensure slug uniqueness
        $baseSlug = $validated['slug'];
        $counter = 1;
        while (Plan::where('slug', $validated['slug'])->exists()) {
            $validated['slug'] = $baseSlug . '-' . $counter++;
        }

        Plan::create($validated);

        return redirect()->route('superadmin.plans.index')
            ->with('success', 'Plan creado exitosamente.');
    }

    public function edit(int $id)
    {
        $plan = Plan::withCount('subscriptions')->findOrFail($id);

        return Inertia::render('SuperAdmin/Plans/Edit', [
            'plan' => $plan,
        ]);
    }

    public function update(Request $request, int $id)
    {
        $plan = Plan::findOrFail($id);

        $validated = $request->validate($this->validationRules($id));

        if (isset($validated['name']) && $validated['name'] !== $plan->name) {
            $validated['slug'] = \Illuminate\Support\Str::slug($validated['name']);
            $baseSlug = $validated['slug'];
            $counter = 1;
            while (Plan::where('slug', $validated['slug'])->where('id', '!=', $id)->exists()) {
                $validated['slug'] = $baseSlug . '-' . $counter++;
            }
        }

        $plan->update($validated);

        return redirect()->route('superadmin.plans.edit', $id)
            ->with('success', 'Plan actualizado exitosamente.');
    }

    public function destroy(int $id)
    {
        $plan = Plan::withCount('subscriptions')->findOrFail($id);

        if ($plan->subscriptions_count > 0) {
            return back()->with('error', 'No se puede eliminar un plan con suscripciones activas.');
        }

        $plan->delete();

        return redirect()->route('superadmin.plans.index')
            ->with('success', 'Plan eliminado exitosamente.');
    }

    public function toggleActive(int $id)
    {
        $plan = Plan::findOrFail($id);
        $plan->update(['is_active' => !$plan->is_active]);

        $status = $plan->is_active ? 'activado' : 'desactivado';

        return back()->with('success', "Plan {$status} exitosamente.");
    }

    private function validationRules(?int $ignoreId = null): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'price_monthly' => ['required', 'numeric', 'min:0'],
            'price_annual' => ['nullable', 'numeric', 'min:0'],
            'trial_days' => ['required', 'integer', 'min:0', 'max:365'],
            'currency' => ['required', 'string', 'size:3'],
            'max_branches' => ['required', 'integer', 'min:1'],
            'max_menu_items' => ['required', 'integer', 'min:1'],
            'max_drivers' => ['required', 'integer', 'min:0'],
            'max_orders_per_month' => ['required', 'integer', 'min:1'],
            'max_users' => ['required', 'integer', 'min:1'],
            'whatsapp_bot_enabled' => ['boolean'],
            'ai_enabled' => ['boolean'],
            'external_menu_enabled' => ['boolean'],
            'custom_domain' => ['boolean'],
            'support_addon_available' => ['boolean'],
            'support_addon_price' => ['nullable', 'numeric', 'min:0'],
            'delivery_app_addon_available' => ['boolean'],
            'delivery_app_addon_price' => ['nullable', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}
