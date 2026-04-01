<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class SharePlanData
{
    public function handle(Request $request, Closure $next): Response
    {
        Inertia::share('plan', function () {
            $tenant = app()->bound('tenant') ? app('tenant') : null;
            if (!$tenant) return null;

            $subscription = $tenant->subscription?->load('plan');
            if (!$subscription) return null;

            $plan = $subscription->plan;

            return [
                'name' => $plan->name,
                'slug' => $plan->slug,
                'status' => $subscription->status,
                'is_trial' => $subscription->status === 'trialing',
                'trial_ends_at' => $subscription->trial_ends_at?->toISOString(),
                'current_period_end' => $subscription->current_period_end?->toISOString(),
                'limits' => [
                    'max_branches' => $plan->max_branches,
                    'max_menu_items' => $plan->max_menu_items,
                    'max_drivers' => $plan->max_drivers,
                    'max_orders_per_month' => $plan->max_orders_per_month,
                    'max_users' => $plan->max_users,
                ],
                'features' => [
                    'whatsapp_bot' => (bool) $plan->whatsapp_bot_enabled,
                    'ai' => (bool) $plan->ai_enabled,
                    'external_menu' => (bool) $plan->external_menu_enabled,
                    'custom_domain' => (bool) $plan->custom_domain,
                ],
                'usage' => [
                    'branches' => $tenant->branches()->count(),
                    'menu_items' => $tenant->menuItems()->count(),
                    'drivers' => \App\Models\Driver::where('tenant_id', $tenant->id)->count(),
                    'users' => $tenant->users()->count(),
                    'orders_this_month' => $tenant->orders()->where('created_at', '>=', now()->startOfMonth())->count(),
                ],
            ];
        });

        return $next($request);
    }
}
