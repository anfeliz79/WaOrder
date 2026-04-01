<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Plan;
use App\Services\Subscription\SubscriptionManager;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BillingController extends Controller
{
    public function __construct(
        private SubscriptionManager $subscriptionManager,
    ) {}

    public function index()
    {
        $tenant = app('tenant');
        $subscription = $tenant->subscription?->load('plan', 'addons');
        $defaultToken = $tenant->defaultCardnetToken;

        $invoices = Invoice::where('tenant_id', $tenant->id)
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        $plans = Plan::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        // Usage stats
        $usage = [
            'branches' => $tenant->branches()->count(),
            'menu_items' => $tenant->menuItems()->count(),
            'drivers' => $tenant->loadCount('branches') ? \App\Models\Driver::where('tenant_id', $tenant->id)->count() : 0,
            'users' => $tenant->users()->count(),
            'orders_this_month' => $tenant->orders()
                ->where('created_at', '>=', now()->startOfMonth())
                ->count(),
        ];

        return Inertia::render('Billing/Index', [
            'subscription' => $subscription,
            'paymentMethod' => $defaultToken ? [
                'brand' => $defaultToken->card_brand,
                'last_four' => $defaultToken->card_last_four,
                'expiry' => $defaultToken->card_expiry,
            ] : null,
            'invoices' => $invoices,
            'plans' => $plans,
            'usage' => $usage,
        ]);
    }

    public function changePlan(Request $request)
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
        ]);

        $tenant = app('tenant');
        $subscription = $tenant->subscription;
        $plan = Plan::findOrFail($validated['plan_id']);

        if (!$subscription) {
            return back()->with('error', 'No tienes una suscripcion activa.');
        }

        $this->subscriptionManager->changePlan($subscription, $plan);

        return back()->with('success', "Plan cambiado a {$plan->name} exitosamente.");
    }

    public function cancel(Request $request)
    {
        $tenant = app('tenant');
        $subscription = $tenant->subscription;

        if (!$subscription || !$subscription->isActive()) {
            return back()->with('error', 'No tienes una suscripcion activa.');
        }

        $reason = $request->input('reason', '');
        $this->subscriptionManager->cancel($subscription, $reason);

        return back()->with('success', 'Suscripcion cancelada. Seguiras teniendo acceso hasta el fin del periodo actual.');
    }

    public function reactivate()
    {
        $tenant = app('tenant');
        $subscription = $tenant->subscription;

        if (!$subscription || !$subscription->isCancelled()) {
            return back()->with('error', 'No hay una suscripcion cancelada para reactivar.');
        }

        $this->subscriptionManager->reactivate($subscription);

        return back()->with('success', 'Suscripcion reactivada exitosamente.');
    }
}
