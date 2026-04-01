<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Order;
use App\Models\Customer;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $monthNames = [
            1 => 'Ene', 2 => 'Feb', 3 => 'Mar', 4 => 'Abr',
            5 => 'May', 6 => 'Jun', 7 => 'Jul', 8 => 'Ago',
            9 => 'Sep', 10 => 'Oct', 11 => 'Nov', 12 => 'Dic',
        ];

        // Build last 6 months as ordered keys (year-month)
        $last6Months = collect();
        for ($i = 5; $i >= 0; $i--) {
            $date = Carbon::now()->subMonths($i);
            $last6Months->put($date->format('Y-m'), [
                'month' => $monthNames[(int) $date->format('n')],
                'count' => 0,
            ]);
        }

        // Tenants by month (last 6 months)
        $tenantRows = DB::table('tenants')
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as total")
            ->where('created_at', '>=', Carbon::now()->subMonths(5)->startOfMonth())
            ->groupBy('ym')
            ->orderBy('ym')
            ->get()
            ->keyBy('ym');

        $tenantsByMonth = $last6Months->map(function ($item, $ym) use ($tenantRows) {
            return [
                'month' => $item['month'],
                'count' => isset($tenantRows[$ym]) ? (int) $tenantRows[$ym]->total : 0,
            ];
        })->values()->all();

        // Orders by month (last 6 months) — withoutGlobalScope bypasses BelongsToTenant
        $orderRows = DB::table('orders')
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as ym, COUNT(*) as total")
            ->where('created_at', '>=', Carbon::now()->subMonths(5)->startOfMonth())
            ->groupBy('ym')
            ->orderBy('ym')
            ->get()
            ->keyBy('ym');

        $ordersByMonth = $last6Months->map(function ($item, $ym) use ($orderRows) {
            return [
                'month' => $item['month'],
                'count' => isset($orderRows[$ym]) ? (int) $orderRows[$ym]->total : 0,
            ];
        })->values()->all();

        // Plan distribution — active subscriptions joined with plans
        $planDistribution = DB::table('subscriptions')
            ->join('plans', 'subscriptions.plan_id', '=', 'plans.id')
            ->whereIn('subscriptions.status', ['active', 'trialing'])
            ->selectRaw('plans.name, plans.slug, COUNT(*) as count')
            ->groupBy('plans.id', 'plans.name', 'plans.slug')
            ->orderBy('plans.sort_order')
            ->get()
            ->map(fn ($row) => [
                'name'  => $row->name,
                'slug'  => $row->slug,
                'count' => (int) $row->count,
            ])
            ->values()
            ->all();

        $stats = [
            'total_tenants'     => Tenant::count(),
            'active_tenants'    => Tenant::where('is_active', true)->count(),
            'total_users'       => User::where('role', '!=', 'superadmin')->count(),
            'total_orders'      => Order::count(),
            'total_customers'   => Customer::count(),
            'recent_tenants'    => Tenant::latest()->take(5)->get(['id', 'name', 'slug', 'is_active', 'subscription_plan', 'created_at']),
            'recent_orders'     => Order::with('tenant:id,name')->latest()->take(10)->get(['id', 'tenant_id', 'status', 'total', 'created_at']),
            'tenants_by_month'  => $tenantsByMonth,
            'orders_by_month'   => $ordersByMonth,
            'plan_distribution' => $planDistribution,
        ];

        return Inertia::render('SuperAdmin/Dashboard', [
            'stats'  => $stats,
            'alerts' => $this->buildAlerts(),
        ]);
    }

    private function buildAlerts(): array
    {
        $alerts = [];

        // past_due subscriptions
        $pastDue = Subscription::withoutGlobalScope('tenant')
            ->where('status', 'past_due')
            ->count();
        if ($pastDue > 0) {
            $alerts[] = [
                'level'        => 'critical',
                'message'      => "{$pastDue} restaurante(s) con pagos vencidos",
                'action_url'   => '/superadmin/tenants',
                'action_label' => 'Ver restaurantes',
            ];
        }

        // pending_payment stuck > 24h
        $stuckPayments = Subscription::withoutGlobalScope('tenant')
            ->where('status', 'pending_payment')
            ->where('created_at', '<', now()->subHours(24))
            ->count();
        if ($stuckPayments > 0) {
            $alerts[] = [
                'level'        => 'warning',
                'message'      => "{$stuckPayments} restaurante(s) con pago pendiente por más de 24h",
                'action_url'   => '/superadmin/tenants',
                'action_label' => 'Ver restaurantes',
            ];
        }

        // Active tenants with setup completed but no WhatsApp configured
        $noWhatsapp = Tenant::whereNull('whatsapp_phone_number_id')
            ->where('is_active', true)
            ->whereRaw("JSON_EXTRACT(settings, '$.setup_completed') = true")
            ->count();
        if ($noWhatsapp > 0) {
            $alerts[] = [
                'level'        => 'info',
                'message'      => "{$noWhatsapp} restaurante(s) activo(s) sin WhatsApp configurado",
                'action_url'   => '/superadmin/tenants',
                'action_label' => 'Ver restaurantes',
            ];
        }

        // Pending migrations
        try {
            $migrationRows = DB::table('migrations')->pluck('migration')->toArray();
            $migrationFiles = glob(database_path('migrations/*.php'));
            $pendingCount = 0;
            foreach ($migrationFiles as $file) {
                $migrationName = pathinfo($file, PATHINFO_FILENAME);
                if (!in_array($migrationName, $migrationRows)) {
                    $pendingCount++;
                }
            }

            if ($pendingCount > 0) {
                $alerts[] = [
                    'level'        => 'critical',
                    'message'      => "{$pendingCount} migración(es) pendiente(s) de ejecutar",
                    'action_url'   => '/superadmin/system',
                    'action_label' => 'Ver sistema',
                ];
            }
        } catch (\Throwable) {
            // Silently skip if migration check fails
        }

        return $alerts;
    }
}
