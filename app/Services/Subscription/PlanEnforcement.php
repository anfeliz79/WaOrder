<?php

namespace App\Services\Subscription;

use App\Models\Driver;
use App\Models\Tenant;

class PlanEnforcement
{
    /**
     * Check if the tenant can create a new branch.
     */
    public function canCreateBranch(Tenant $tenant): true|string
    {
        return $this->checkLimit(
            $tenant,
            'max_branches',
            $tenant->branches()->count(),
            'sucursales',
        );
    }

    /**
     * Check if the tenant can create a new menu item.
     */
    public function canCreateMenuItem(Tenant $tenant): true|string
    {
        return $this->checkLimit(
            $tenant,
            'max_menu_items',
            $tenant->menuItems()->count(),
            'productos en el menú',
        );
    }

    /**
     * Check if the tenant can create a new driver.
     */
    public function canCreateDriver(Tenant $tenant): true|string
    {
        return $this->checkLimit(
            $tenant,
            'max_drivers',
            Driver::where('tenant_id', $tenant->id)->count(),
            'repartidores',
        );
    }

    /**
     * Check if the tenant can create a new user.
     */
    public function canCreateUser(Tenant $tenant): true|string
    {
        return $this->checkLimit(
            $tenant,
            'max_users',
            $tenant->users()->count(),
            'usuarios',
        );
    }

    /**
     * Check if the tenant can accept a new order this month.
     */
    public function canAcceptOrder(Tenant $tenant): true|string
    {
        $ordersThisMonth = $tenant->orders()
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        return $this->checkLimit(
            $tenant,
            'max_orders_per_month',
            $ordersThisMonth,
            'pedidos este mes',
        );
    }

    /**
     * Check if a specific feature is enabled for the tenant's plan.
     */
    public function isFeatureEnabled(Tenant $tenant, string $feature): bool
    {
        $plan = $this->getActivePlan($tenant);

        if (! $plan) {
            return false;
        }

        return (bool) ($plan->{$feature} ?? false);
    }

    /**
     * Get a summary of current usage vs plan limits.
     */
    public function getUsageSummary(Tenant $tenant): array
    {
        $plan = $this->getActivePlan($tenant);

        $ordersThisMonth = $tenant->orders()
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        return [
            'branches' => [
                'used' => $tenant->branches()->count(),
                'limit' => $plan?->max_branches,
            ],
            'menu_items' => [
                'used' => $tenant->menuItems()->count(),
                'limit' => $plan?->max_menu_items,
            ],
            'drivers' => [
                'used' => Driver::where('tenant_id', $tenant->id)->count(),
                'limit' => $plan?->max_drivers,
            ],
            'users' => [
                'used' => $tenant->users()->count(),
                'limit' => $plan?->max_users,
            ],
            'orders_this_month' => [
                'used' => $ordersThisMonth,
                'limit' => $plan?->max_orders_per_month,
            ],
        ];
    }

    /**
     * Get the plan limits and feature flags.
     */
    public function getPlanLimits(Tenant $tenant): array
    {
        $plan = $this->getActivePlan($tenant);

        if (! $plan) {
            return [
                'plan_name' => null,
                'limits' => [],
                'features' => [],
            ];
        }

        return [
            'plan_name' => $plan->name,
            'limits' => [
                'max_branches' => $plan->max_branches,
                'max_menu_items' => $plan->max_menu_items,
                'max_drivers' => $plan->max_drivers,
                'max_orders_per_month' => $plan->max_orders_per_month,
                'max_users' => $plan->max_users,
            ],
            'features' => [
                'whatsapp_bot_enabled' => (bool) $plan->whatsapp_bot_enabled,
                'ai_enabled' => (bool) $plan->ai_enabled,
                'external_menu_enabled' => (bool) $plan->external_menu_enabled,
                'custom_domain' => (bool) $plan->custom_domain,
            ],
        ];
    }

    /**
     * Validate a numeric limit against current usage.
     *
     * Returns true if allowed, or a Spanish error string if the limit is reached.
     */
    private function checkLimit(Tenant $tenant, string $limitField, int $currentCount, string $resourceLabel): true|string
    {
        $plan = $this->getActivePlan($tenant);

        if (! $plan) {
            return 'No tienes una suscripción activa. Activa un plan para continuar.';
        }

        $limit = $plan->{$limitField};

        // null or 0 means unlimited
        if (empty($limit)) {
            return true;
        }

        if ($currentCount >= $limit) {
            return "Has alcanzado el límite de {$resourceLabel} de tu plan ({$limit}). Actualiza tu plan para continuar.";
        }

        return true;
    }

    /**
     * Resolve the active plan for a tenant via its subscription.
     */
    private function getActivePlan(Tenant $tenant): ?\App\Models\Plan
    {
        $subscription = $tenant->subscription;

        if (! $subscription || ! $subscription->isActive()) {
            return null;
        }

        return $subscription->plan;
    }
}
