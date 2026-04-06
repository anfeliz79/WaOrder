<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\MenuItem;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $today = now()->startOfDay();
        $branch = app()->bound('branch') ? app('branch') : null;

        $ordersToday = Order::whereDate('created_at', $today);
        if ($branch) {
            $ordersToday->where('branch_id', $branch->id);
        }

        $activeOrders = Order::active();
        if ($branch) {
            $activeOrders->where('branch_id', $branch->id);
        }

        $stats = [
            'orders_today' => (clone $ordersToday)->count(),
            'revenue_today' => (float) (clone $ordersToday)
                ->whereNotIn('status', ['cancelled'])
                ->sum('total'),
            'active_orders' => $activeOrders->count(),
            'avg_time' => $this->averageFulfillmentTime($branch?->id),
        ];

        $recentQuery = Order::with('items')->latest()->take(20);
        if ($branch) {
            $recentQuery->where('branch_id', $branch->id);
        }

        $recentOrders = $recentQuery->get()->map(fn ($order) => [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'customer_name' => $order->customer_name,
            'total' => $order->total,
            'status' => $order->status,
            'items_count' => $order->items->count(),
            'created_at' => $order->created_at->format('H:i'),
        ]);

        return Inertia::render('Dashboard', [
            'stats' => $stats,
            'recentOrders' => $recentOrders,
            'onboarding_checklist' => $this->getOnboardingChecklist(),
        ]);
    }

    public function dismissOnboarding(Request $request)
    {
        $tenant = app('tenant');
        $settings = $tenant->settings ?? [];
        $settings['onboarding_dismissed'] = true;
        $tenant->update(['settings' => $settings]);

        Cache::forget("onboarding_checklist_{$tenant->id}");

        return back()->with('success', 'Guía de inicio ocultada.');
    }

    public function liveOrders()
    {
        $branch = app()->bound('branch') ? app('branch') : null;

        $query = Order::active()->with('items')->latest();
        if ($branch) {
            $query->where('branch_id', $branch->id);
        }

        $orders = $query->get()->map(fn ($order) => [
            'id' => $order->id,
            'order_number' => $order->order_number,
            'customer_name' => $order->customer_name,
            'customer_phone' => $order->customer_phone,
            'total' => $order->total,
            'status' => $order->status,
            'delivery_type' => $order->delivery_type,
            'items_count' => $order->items->count(),
            'created_at' => $order->created_at->toISOString(),
            'confirmed_at' => $order->confirmed_at?->toISOString(),
        ]);

        return response()->json($orders);
    }

    private function getOnboardingChecklist(): ?array
    {
        $tenant = app()->bound('tenant') ? app('tenant') : null;
        if (!$tenant) {
            return null;
        }

        // Don't show if already dismissed
        if ($tenant->getSetting('onboarding_dismissed', false)) {
            return null;
        }

        return Cache::remember("onboarding_checklist_{$tenant->id}", 300, function () use ($tenant) {
            $hasWhatsApp = !empty($tenant->whatsapp_phone_number_id)
                && $tenant->whatsapp_phone_number_id !== 'DEMO_PHONE_ID';

            $hasBranch = Branch::where('tenant_id', $tenant->id)
                ->where('is_active', true)
                ->exists();

            $menuSource = $tenant->getMenuSource();
            $hasMenu = false;
            if ($menuSource === 'external') {
                $hasMenu = !empty($tenant->getSetting('menu_api_url'));
            } else {
                $hasMenu = MenuItem::where('tenant_id', $tenant->id)
                    ->where('is_active', true)
                    ->exists();
            }

            $paymentMethods = $tenant->getSetting('payment.methods', []);
            $hasPayment = !empty($paymentMethods);

            $hasBusinessHours = (bool) $tenant->getSetting('business_hours.enabled', false);

            $hasRestaurantName = !empty($tenant->name) && $tenant->name !== 'Mi Restaurante';

            $items = [
                [
                    'key' => 'restaurant_info',
                    'title' => 'Datos del restaurante',
                    'description' => 'Nombre y datos basicos configurados',
                    'link' => '/settings',
                    'completed' => $hasRestaurantName,
                ],
                [
                    'key' => 'whatsapp',
                    'title' => 'WhatsApp configurado',
                    'description' => 'Credenciales de la API de WhatsApp',
                    'link' => '/settings',
                    'completed' => $hasWhatsApp,
                ],
                [
                    'key' => 'branch',
                    'title' => 'Sucursal creada',
                    'description' => 'Al menos una sucursal activa',
                    'link' => '/branches',
                    'completed' => $hasBranch,
                ],
                [
                    'key' => 'menu',
                    'title' => 'Menu con productos',
                    'description' => 'Productos disponibles para pedidos',
                    'link' => '/menu',
                    'completed' => $hasMenu,
                ],
                [
                    'key' => 'payment',
                    'title' => 'Metodos de pago',
                    'description' => 'Configura como recibiras pagos',
                    'link' => '/settings',
                    'completed' => $hasPayment,
                ],
                [
                    'key' => 'business_hours',
                    'title' => 'Horario de negocio',
                    'description' => 'Define tu horario de atencion',
                    'link' => '/settings',
                    'completed' => $hasBusinessHours,
                ],
            ];

            $completedCount = collect($items)->where('completed', true)->count();
            $totalCount = count($items);

            return [
                'items' => $items,
                'progress' => [
                    'completed' => $completedCount,
                    'total' => $totalCount,
                    'percentage' => $totalCount > 0 ? round(($completedCount / $totalCount) * 100) : 0,
                ],
            ];
        });
    }

    private function averageFulfillmentTime(?int $branchId = null): int
    {
        $query = Order::where('status', 'delivered')
            ->whereDate('created_at', now()->startOfDay())
            ->whereNotNull('confirmed_at')
            ->whereNotNull('completed_at');

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $orders = $query->get(['confirmed_at', 'completed_at']);

        if ($orders->isEmpty()) {
            return 0;
        }

        $totalMinutes = $orders->sum(function ($order) {
            return $order->confirmed_at->diffInMinutes($order->completed_at);
        });

        return (int) ($totalMinutes / $orders->count());
    }
}
