<?php

namespace App\Http\Middleware;

use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    private function getCriticalAlertCount(): int
    {
        return Cache::remember('superadmin_critical_alert_count', 60, function () {
            return Subscription::withoutGlobalScope('tenant')
                ->where('status', 'past_due')
                ->count();
        });
    }

    private function resolveSubscriptionAlert($user, $tenant): ?array
    {
        if (!$user || $user->isSuperAdmin() || !$tenant) {
            return null;
        }

        return $tenant->getSubscriptionAlert();
    }

    public function share(Request $request): array
    {
        $user = $request->user();

        $branches = [];
        $currentBranch = null;
        $permissions = [];

        if ($user) {
            if ($user->isSuperAdmin()) {
                // SuperAdmin: no branches, full permissions
                $permissions = ['*', 'superadmin'];
            } else {
                // Load accessible branches
                if ($user->isAdmin()) {
                    $tenant = app()->bound('tenant') ? app('tenant') : null;
                    if ($tenant) {
                        $branches = $tenant->branches()
                            ->active()
                            ->orderBy('sort_order')
                            ->get(['id', 'name'])
                            ->toArray();
                    }
                } else {
                    $branches = $user->branches()
                        ->where('is_active', true)
                        ->orderBy('sort_order')
                        ->get(['branches.id', 'branches.name'])
                        ->toArray();
                }

                // Current branch
                $branch = app()->bound('branch') ? app('branch') : null;
                if ($branch) {
                    $currentBranch = ['id' => $branch->id, 'name' => $branch->name];
                }

                // Permissions based on role
                if ($user->isAdmin()) {
                    $permissions = ['*'];
                } elseif ($user->isOrderTaker()) {
                    $permissions = ['console.view', 'orders.view', 'orders.manage'];
                } else {
                    $permissions = ['dashboard.view', 'orders.view', 'orders.manage', 'customers.view'];
                }
            }
        }

        $tenant = app()->bound('tenant') ? app('tenant') : null;

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? array_merge($user->only('id', 'name', 'email', 'role'), [
                    'is_superadmin' => $user->isSuperAdmin(),
                ]) : null,
                'branches' => $branches,
                'current_branch' => $currentBranch,
                'permissions' => $permissions,
                'impersonating' => $request->session()->has('impersonating_id'),
                'tenant_name' => $request->session()->has('impersonating_id')
                    ? ($tenant?->name ?? null)
                    : null,
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ],
            'subscription_alert' => $this->resolveSubscriptionAlert($user, $tenant),
            'alert_count' => fn () => $this->getCriticalAlertCount(),
            'notification_settings' => [
                'sound_enabled' => $tenant?->getSetting('notifications.sound_enabled', false),
                'polling_interval' => $tenant?->getSetting('notifications.polling_interval', 20),
                'custom_sound_url' => ($soundPath = $tenant?->getSetting('notifications.custom_sound_path'))
                    ? asset("storage/{$soundPath}")
                    : null,
            ],
        ]);
    }
}
