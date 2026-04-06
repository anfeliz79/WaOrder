<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $query = Subscription::withoutGlobalScope('tenant')
            ->with(['tenant', 'plan']);

        if ($status = $request->input('status')) {
            $query->where('status', $status);
        }

        if ($planId = $request->input('plan_id')) {
            $query->where('plan_id', $planId);
        }

        if ($search = $request->input('search')) {
            $query->whereHas('tenant', fn ($q) => $q->where('name', 'like', "%{$search}%"));
        }

        $subscriptions = $query->latest()->paginate(15)->withQueryString();

        $plans = Plan::orderBy('sort_order')->get(['id', 'name']);

        return Inertia::render('SuperAdmin/Subscriptions/Index', [
            'subscriptions' => $subscriptions,
            'plans' => $plans,
            'filters' => $request->only(['status', 'plan_id', 'search']),
        ]);
    }

    public function show(int $subscription)
    {
        $sub = Subscription::withoutGlobalScope('tenant')
            ->with([
                'tenant',
                'plan',
                'addons',
                'invoices' => fn ($q) => $q->withoutGlobalScope('tenant')->latest()->limit(20),
            ])
            ->findOrFail($subscription);

        $plans = Plan::where('is_active', true)->orderBy('sort_order')->get(['id', 'name', 'price_monthly', 'price_annual', 'currency']);

        return Inertia::render('SuperAdmin/Subscriptions/Show', [
            'subscription' => $sub,
            'plans' => $plans,
        ]);
    }

    public function extend(int $subscription)
    {
        $sub = Subscription::withoutGlobalScope('tenant')->with('tenant')->findOrFail($subscription);

        $newEnd = ($sub->current_period_end && $sub->current_period_end->isFuture())
            ? $sub->current_period_end->addMonth()
            : now()->addMonth();

        $sub->update(['current_period_end' => $newEnd]);

        return back()->with('success', "Período extendido hasta {$newEnd->format('d/m/Y')} para {$sub->tenant->name}.");
    }

    public function cancel(int $subscription, Request $request)
    {
        $sub = Subscription::withoutGlobalScope('tenant')->with('tenant')->findOrFail($subscription);

        $sub->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $request->input('reason'),
        ]);

        return back()->with('success', "Suscripción de {$sub->tenant->name} cancelada.");
    }

    public function reactivate(int $subscription)
    {
        $sub = Subscription::withoutGlobalScope('tenant')->with('tenant')->findOrFail($subscription);

        $newEnd = now()->addMonth();

        $sub->update([
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => $newEnd,
            'cancelled_at' => null,
            'cancellation_reason' => null,
        ]);

        return back()->with('success', "Suscripción de {$sub->tenant->name} reactivada hasta {$newEnd->format('d/m/Y')}.");
    }

    public function changePlan(int $subscription, Request $request)
    {
        $request->validate([
            'plan_id' => 'required|exists:plans,id',
            'billing_period' => 'required|in:monthly,annual',
        ]);

        $sub = Subscription::withoutGlobalScope('tenant')->with('tenant')->findOrFail($subscription);
        $plan = Plan::findOrFail($request->input('plan_id'));

        $price = $plan->getPriceForPeriod($request->input('billing_period'));

        $sub->update([
            'plan_id' => $plan->id,
            'billing_period' => $request->input('billing_period'),
            'price' => $price,
        ]);

        return back()->with('success', "Plan de {$sub->tenant->name} cambiado a {$plan->name} ({$request->input('billing_period')}).");
    }

    public function updateNotes(int $subscription, Request $request)
    {
        $request->validate(['admin_notes' => 'nullable|string|max:2000']);

        $sub = Subscription::withoutGlobalScope('tenant')->with('tenant')->findOrFail($subscription);

        $sub->update(['admin_notes' => $request->input('admin_notes')]);

        return back()->with('success', 'Notas actualizadas.');
    }
}
