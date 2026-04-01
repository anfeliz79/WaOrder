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

        $subscriptions = $query->latest()->paginate(15)->withQueryString();

        $plans = Plan::orderBy('sort_order')->get(['id', 'name']);

        return Inertia::render('SuperAdmin/Subscriptions/Index', [
            'subscriptions' => $subscriptions,
            'plans' => $plans,
            'filters' => $request->only(['status', 'plan_id']),
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

    public function cancel(int $subscription)
    {
        $sub = Subscription::withoutGlobalScope('tenant')->with('tenant')->findOrFail($subscription);

        $sub->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return back()->with('success', "Suscripción de {$sub->tenant->name} cancelada.");
    }

    public function reactivate(int $subscription)
    {
        $sub = Subscription::withoutGlobalScope('tenant')->with('tenant')->findOrFail($subscription);

        $newEnd = now()->addMonth();

        $sub->update([
            'status' => 'active',
            'current_period_end' => $newEnd,
            'cancelled_at' => null,
        ]);

        return back()->with('success', "Suscripción de {$sub->tenant->name} reactivada hasta {$newEnd->format('d/m/Y')}.");
    }
}
