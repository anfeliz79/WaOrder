<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use App\Models\Driver;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Models\TenantNotice;
use App\Models\TransferVerification;
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

    private function getPendingTransfersCount(): int
    {
        return Cache::remember('superadmin_pending_transfers_count', 120, function () {
            return TransferVerification::withoutGlobalScope('tenant')
                ->where('status', 'pending')
                ->count();
        });
    }

    private function resolveTenantNotices($user, $tenant): array
    {
        if (!$user || $user->isSuperAdmin() || !$tenant) {
            return [];
        }

        return Cache::remember("tenant_notices_{$tenant->id}", 300, function () use ($tenant) {
            return TenantNotice::active()
                ->forTenant($tenant->id)
                ->orderByRaw("FIELD(type, 'danger', 'warning', 'info', 'success')")
                ->limit(5)
                ->get(['id', 'title', 'message', 'type', 'dismissible'])
                ->toArray();
        });
    }

    private function resolveSetupAlerts($user): array
    {
        if (!$user || $user->isSuperAdmin() || !$user->tenant_id) {
            return [];
        }

        return Cache::remember("setup_alerts_{$user->tenant_id}", 300, function () use ($user) {
            $tenant = Tenant::find($user->tenant_id);
            if (!$tenant) {
                return [];
            }
            $alerts = [];

            // 1. WhatsApp credentials
            $phoneId = $tenant->whatsapp_phone_number_id;
            if (empty($phoneId) || $phoneId === 'DEMO_PHONE_ID') {
                $alerts[] = [
                    'message' => 'Configura las credenciales de WhatsApp para activar el bot.',
                    'link' => '/settings',
                    'link_text' => 'Ir a Configuracion',
                ];
            }

            // 2. Branches (must have at least 1 active)
            $activeBranches = Branch::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->count();
            if ($activeBranches === 0) {
                $alerts[] = [
                    'message' => 'Crea al menos una sucursal para recibir pedidos.',
                    'link' => '/branches',
                    'link_text' => 'Crear sucursal',
                ];
            }

            // 3. Menu
            $menuSource = $tenant->getMenuSource();
            if ($menuSource === 'internal') {
                $activeItems = MenuItem::withoutGlobalScope('tenant')
                    ->where('tenant_id', $tenant->id)
                    ->where('is_active', true)
                    ->count();
                if ($activeItems === 0) {
                    $alerts[] = [
                        'message' => 'Agrega productos al menu para que los clientes puedan pedir.',
                        'link' => '/menu',
                        'link_text' => 'Ir al menu',
                    ];
                }
            } elseif ($menuSource === 'external') {
                if (empty($tenant->getSetting('menu_api_url'))) {
                    $alerts[] = [
                        'message' => 'Configura la URL de la API del menu externo.',
                        'link' => '/settings',
                        'link_text' => 'Ir a Configuracion',
                    ];
                }
            }

            return $alerts;
        });
    }

    private function resolveBotStatus($user): ?array
    {
        if (!$user || $user->isSuperAdmin() || !$user->tenant_id) {
            return null;
        }

        return Cache::remember("bot_status_{$user->tenant_id}", 60, function () use ($user) {
            $tenant = Tenant::withoutGlobalScope('tenant')->find($user->tenant_id);
            if (!$tenant) return null;

            // 1. Check WhatsApp credentials
            $phoneId = $tenant->whatsapp_phone_number_id;
            if (empty($phoneId) || $phoneId === 'DEMO_PHONE_ID') {
                return ['is_active' => false, 'is_paused' => false, 'reason' => 'no_credentials'];
            }

            // 2. Check manual pause
            $isPaused = (bool) ($tenant->settings['bot_paused'] ?? false);
            if ($isPaused) {
                return ['is_active' => false, 'is_paused' => true, 'reason' => 'manually_paused'];
            }

            // 3. Check business hours
            $hours = $tenant->getSetting('business_hours', []);
            if (!empty($hours['enabled'])) {
                $tz = $tenant->timezone ?? 'America/Santo_Domingo';
                $now = now($tz);
                $currentDay = (int) $now->format('w');
                $currentTime = $now->format('H:i');
                $openDays = $hours['days'] ?? [1, 2, 3, 4, 5, 6];
                $openTime = $hours['open'] ?? '08:00';
                $closeTime = $hours['close'] ?? '22:00';
                $isClosed = !in_array($currentDay, $openDays) || $currentTime < $openTime || $currentTime >= $closeTime;
                if ($isClosed) {
                    return ['is_active' => false, 'is_paused' => false, 'reason' => 'outside_hours'];
                }
            }

            return ['is_active' => true, 'is_paused' => false, 'reason' => null];
        });
    }

    private function resolvePlanUsage($user): array
    {
        if (!$user || $user->isSuperAdmin() || !$user->tenant_id) {
            return [];
        }

        return Cache::remember("plan_usage_{$user->tenant_id}", 300, function () use ($user) {
            $tenant = Tenant::find($user->tenant_id);
            if (!$tenant) return [];

            $subscription = Subscription::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)->first();
            if (!$subscription || !$subscription->isActive()) return [];

            $plan = $subscription->plan;
            if (!$plan) return [];

            $branchCount = Branch::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count();
            $menuItemCount = MenuItem::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count();
            $userCount = User::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count();
            $driverCount = Driver::withoutGlobalScope('tenant')->where('tenant_id', $tenant->id)->count();
            $ordersThisMonth = Order::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->where('created_at', '>=', now()->startOfMonth())->count();

            return [
                'plan_name' => $plan->name,
                'branches' => ['used' => $branchCount, 'limit' => $plan->max_branches, 'unlimited' => empty($plan->max_branches)],
                'menu_items' => ['used' => $menuItemCount, 'limit' => $plan->max_menu_items, 'unlimited' => empty($plan->max_menu_items)],
                'users' => ['used' => $userCount, 'limit' => $plan->max_users, 'unlimited' => empty($plan->max_users)],
                'drivers' => ['used' => $driverCount, 'limit' => $plan->max_drivers, 'unlimited' => empty($plan->max_drivers)],
                'orders_this_month' => ['used' => $ordersThisMonth, 'limit' => $plan->max_orders_per_month, 'unlimited' => empty($plan->max_orders_per_month)],
            ];
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
                    $permissions = ['dashboard.view', 'orders.view', 'orders.manage', 'customers.view', 'reports.view'];
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
            'bot_status' => fn () => $this->resolveBotStatus($user),
            'plan_usage' => fn () => $this->resolvePlanUsage($user),
            'subscription_alert' => $this->resolveSubscriptionAlert($user, $tenant),
            'setup_alerts' => $this->resolveSetupAlerts($user),
            'tenant_notices' => fn () => $this->resolveTenantNotices($user, $tenant),
            'alert_count'              => fn () => $this->getCriticalAlertCount(),
            'pending_transfers_count'  => fn () => ($user?->isSuperAdmin()) ? $this->getPendingTransfersCount() : 0,
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
