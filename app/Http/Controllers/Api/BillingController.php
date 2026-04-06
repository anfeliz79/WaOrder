<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\SubscriptionAddon;
use App\Services\Payment\PayPalService;
use App\Services\Subscription\SubscriptionManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
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

        // Build payment method info — card token or PayPal
        $paymentMethodInfo = null;
        if ($defaultToken) {
            $paymentMethodInfo = [
                'type' => 'cardnet',
                'brand' => $defaultToken->card_brand,
                'last_four' => $defaultToken->card_last_four,
                'expiry' => $defaultToken->card_expiry,
            ];
        } elseif ($subscription?->payment_method === 'paypal' && $subscription?->paypal_subscription_id) {
            $paymentMethodInfo = [
                'type' => 'paypal',
                'paypal_subscription_id' => $subscription->paypal_subscription_id,
            ];
        } elseif ($subscription?->payment_method === 'bank_transfer') {
            $paymentMethodInfo = [
                'type' => 'bank_transfer',
            ];
        }

        return Inertia::render('Billing/Index', [
            'subscription' => $subscription,
            'paymentMethod' => $paymentMethodInfo,
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
        $newPlan = Plan::findOrFail($validated['plan_id']);

        if (!$subscription) {
            return back()->with('error', 'No tienes una suscripcion activa.');
        }

        if ($subscription->plan_id === $newPlan->id) {
            return back()->with('error', 'Ya estas en este plan.');
        }

        $newBasePrice = $newPlan->getPriceForPeriod($subscription->billing_period);

        // Deactivate addons that aren't available in the new plan
        $this->deactivateIncompatibleAddons($subscription, $newPlan);

        // Calculate total including remaining active addons
        $activeAddonTotal = $subscription->addons()->where('is_active', true)->sum('price');
        $newTotal = $newBasePrice + $activeAddonTotal;

        // PayPal: revise the subscription amount
        if ($subscription->payment_method === 'paypal' && $subscription->paypal_subscription_id) {
            try {
                $paypal = app(PayPalService::class);
                $result = $paypal->reviseSubscription(
                    $subscription->paypal_subscription_id,
                    $newTotal,
                    $newPlan->currency ?? 'USD',
                    $subscription->billing_period ?? 'monthly',
                    url("/billing/plan-change/paypal-approved?plan_id={$newPlan->id}"),
                    url('/billing?plan_change_cancelled=1'),
                );

                if ($result['requires_approval']) {
                    Cache::put(
                        "pending_plan_change_{$subscription->id}",
                        ['plan_id' => $newPlan->id, 'new_price' => $newBasePrice],
                        3600
                    );

                    return Inertia::location($result['approval_url']);
                }
            } catch (\Exception $e) {
                Log::error('PayPal plan change revision failed', [
                    'subscription_id' => $subscription->id,
                    'new_plan_id' => $newPlan->id,
                    'error' => $e->getMessage(),
                ]);

                return back()->with('error', 'Error al procesar cambio con PayPal: ' . $e->getMessage());
            }
        }

        // Non-PayPal or no approval needed (downgrade): apply immediately
        $this->subscriptionManager->changePlan($subscription, $newPlan);

        return back()->with('success', "Plan cambiado a {$newPlan->name} exitosamente.");
    }

    public function planChangePayPalCallback(Request $request)
    {
        $planId = $request->query('plan_id');
        $newPlan = $planId ? Plan::find($planId) : null;

        if (!$newPlan) {
            return redirect('/billing')->with('error', 'Plan no encontrado.');
        }

        $tenant = app('tenant');
        $subscription = $tenant->subscription;

        if (!$subscription) {
            return redirect('/billing')->with('error', 'Suscripcion no encontrada.');
        }

        // Clear cache
        Cache::pull("pending_plan_change_{$subscription->id}");

        // Verify PayPal subscription is still active
        if ($subscription->paypal_subscription_id) {
            try {
                $paypal = app(PayPalService::class);
                $paypalSub = $paypal->getSubscription($subscription->paypal_subscription_id);

                if (!in_array($paypalSub['status'] ?? '', ['ACTIVE', 'APPROVED'])) {
                    return redirect('/billing')->with('error', 'La revision de PayPal no fue aprobada.');
                }
            } catch (\Exception $e) {
                Log::error('PayPal plan change callback verification failed', ['error' => $e->getMessage()]);
                return redirect('/billing')->with('error', 'Error verificando con PayPal.');
            }
        }

        // Apply the plan change
        $this->subscriptionManager->changePlan($subscription, $newPlan);

        return redirect('/billing')->with('success', "Plan cambiado a {$newPlan->name} exitosamente.");
    }

    public function cancel(Request $request)
    {
        $tenant = app('tenant');
        $subscription = $tenant->subscription;

        if (!$subscription || !$subscription->isActive()) {
            return back()->with('error', 'No tienes una suscripcion activa.');
        }

        // If subscription is via PayPal, also cancel it on PayPal's side
        if ($subscription->paypal_subscription_id) {
            try {
                app(PayPalService::class)->cancelSubscription(
                    $subscription->paypal_subscription_id,
                    $request->input('reason', 'Cancelled by user')
                );
            } catch (\Exception $e) {
                Log::warning('Failed to cancel PayPal subscription', [
                    'paypal_subscription_id' => $subscription->paypal_subscription_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Deactivate all addons
        $subscription->addons()->where('is_active', true)->update(['is_active' => false]);

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

        // PayPal subscriptions cannot be reactivated after cancellation on PayPal's side
        if ($subscription->payment_method === 'paypal' && $subscription->paypal_subscription_id) {
            return back()->with('error', 'Las suscripciones de PayPal canceladas no se pueden reactivar. Debes crear una nueva suscripcion desde la pagina de registro.');
        }

        $this->subscriptionManager->reactivate($subscription);

        return back()->with('success', 'Suscripcion reactivada exitosamente.');
    }

    public function toggleAddon(Request $request)
    {
        $validated = $request->validate([
            'addon_type' => ['required', 'in:support,delivery_app'],
            'action' => ['required', 'in:activate,deactivate'],
        ]);

        $tenant = app('tenant');
        $subscription = $tenant->subscription;

        if (!$subscription || !$subscription->isActive()) {
            return back()->with('error', 'Necesitas una suscripcion activa para gestionar addons.');
        }

        $plan = $subscription->plan;
        $addonType = $validated['addon_type'];

        $addonAvailable = match ($addonType) {
            'support' => $plan->support_addon_available,
            'delivery_app' => $plan->delivery_app_addon_available,
        };

        if (!$addonAvailable) {
            return back()->with('error', 'Este addon no esta disponible en tu plan actual.');
        }

        $addonPrice = (float) match ($addonType) {
            'support' => $plan->support_addon_price,
            'delivery_app' => $plan->delivery_app_addon_price,
        };

        if ($validated['action'] === 'activate') {
            return $this->activateAddon($subscription, $addonType, $addonPrice);
        }

        return $this->deactivateAddon($subscription, $addonType, $addonPrice);
    }

    private function activateAddon($subscription, string $addonType, float $addonPrice)
    {
        $existing = $subscription->addons()
            ->where('addon_type', $addonType)
            ->where('is_active', true)
            ->first();

        if ($existing) {
            return back()->with('error', 'Este addon ya esta activo.');
        }

        // PayPal subscriptions: revise the subscription amount
        if ($subscription->payment_method === 'paypal' && $subscription->paypal_subscription_id) {
            $newTotal = $this->calculateTotal($subscription, $addonType, $addonPrice, 'add');

            try {
                $paypal = app(PayPalService::class);
                $result = $paypal->reviseSubscription(
                    $subscription->paypal_subscription_id,
                    $newTotal,
                    $subscription->plan->currency ?? 'USD',
                    $subscription->billing_period ?? 'monthly',
                    url("/billing/addon/paypal-approved?addon_type={$addonType}"),
                    url('/billing?addon_cancelled=1'),
                );

                if ($result['requires_approval']) {
                    // Store pending addon in cache for callback/webhook
                    Cache::put(
                        "pending_addon_{$subscription->id}",
                        ['addon_type' => $addonType, 'price' => $addonPrice],
                        3600
                    );

                    return Inertia::location($result['approval_url']);
                }
            } catch (\Exception $e) {
                Log::error('PayPal addon revision failed', [
                    'subscription_id' => $subscription->id,
                    'addon_type' => $addonType,
                    'error' => $e->getMessage(),
                ]);

                return back()->with('error', 'Error al procesar con PayPal: ' . $e->getMessage());
            }
        }

        // Non-PayPal or no approval needed: activate immediately
        SubscriptionAddon::updateOrCreate(
            ['subscription_id' => $subscription->id, 'addon_type' => $addonType],
            ['price' => $addonPrice, 'is_active' => true]
        );

        // Create addon invoice
        Invoice::create([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'type' => 'addon',
            'status' => 'paid',
            'amount' => $addonPrice,
            'tax' => 0,
            'total' => $addonPrice,
            'currency' => $subscription->plan->currency ?? 'USD',
            'payment_method' => $subscription->payment_method ?? 'manual',
            'paid_at' => now(),
            'description' => $this->addonLabel($addonType) . ' - Activacion',
        ]);

        return back()->with('success', $this->addonLabel($addonType) . ' activado exitosamente.');
    }

    private function deactivateAddon($subscription, string $addonType, float $addonPrice)
    {
        $addon = $subscription->addons()
            ->where('addon_type', $addonType)
            ->where('is_active', true)
            ->first();

        if (!$addon) {
            return back()->with('error', 'Este addon no esta activo.');
        }

        // PayPal: revise subscription to lower amount (no approval needed)
        if ($subscription->payment_method === 'paypal' && $subscription->paypal_subscription_id) {
            $newTotal = $this->calculateTotal($subscription, $addonType, $addonPrice, 'remove');

            try {
                $paypal = app(PayPalService::class);
                $paypal->reviseSubscription(
                    $subscription->paypal_subscription_id,
                    $newTotal,
                    $subscription->plan->currency ?? 'USD',
                    $subscription->billing_period ?? 'monthly',
                    url('/billing'),
                    url('/billing'),
                );
            } catch (\Exception $e) {
                Log::warning('PayPal addon deactivation revision failed', [
                    'subscription_id' => $subscription->id,
                    'addon_type' => $addonType,
                    'error' => $e->getMessage(),
                ]);
                // Continue deactivation even if PayPal revision fails
            }
        }

        $addon->update(['is_active' => false]);

        return back()->with('success', $this->addonLabel($addonType) . ' desactivado.');
    }

    public function addonPayPalCallback(Request $request)
    {
        $addonType = $request->query('addon_type');

        if (!$addonType || !in_array($addonType, ['support', 'delivery_app'])) {
            return redirect('/billing')->with('error', 'Addon no valido.');
        }

        $tenant = app('tenant');
        $subscription = $tenant->subscription;

        if (!$subscription) {
            return redirect('/billing')->with('error', 'Suscripcion no encontrada.');
        }

        // Pull pending addon from cache
        $pending = Cache::pull("pending_addon_{$subscription->id}");
        $price = $pending['price'] ?? 0;

        if (!$price) {
            // Fallback: get price from plan
            $plan = $subscription->plan;
            $price = (float) match ($addonType) {
                'support' => $plan->support_addon_price,
                'delivery_app' => $plan->delivery_app_addon_price,
                default => 0,
            };
        }

        // Verify PayPal subscription is still active
        if ($subscription->paypal_subscription_id) {
            try {
                $paypal = app(PayPalService::class);
                $paypalSub = $paypal->getSubscription($subscription->paypal_subscription_id);

                if (!in_array($paypalSub['status'] ?? '', ['ACTIVE', 'APPROVED'])) {
                    return redirect('/billing')->with('error', 'La revision de PayPal no fue aprobada.');
                }
            } catch (\Exception $e) {
                Log::error('PayPal addon callback verification failed', ['error' => $e->getMessage()]);
                return redirect('/billing')->with('error', 'Error verificando con PayPal.');
            }
        }

        // Activate the addon
        SubscriptionAddon::updateOrCreate(
            ['subscription_id' => $subscription->id, 'addon_type' => $addonType],
            ['price' => $price, 'is_active' => true]
        );

        // Create invoice
        Invoice::create([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'type' => 'addon',
            'status' => 'paid',
            'amount' => $price,
            'tax' => 0,
            'total' => $price,
            'currency' => $subscription->plan->currency ?? 'USD',
            'payment_method' => 'paypal',
            'paid_at' => now(),
            'description' => $this->addonLabel($addonType) . ' - Activacion via PayPal',
            'metadata' => ['paypal_subscription_id' => $subscription->paypal_subscription_id],
        ]);

        return redirect('/billing')->with('success', $this->addonLabel($addonType) . ' activado exitosamente.');
    }

    private function deactivateIncompatibleAddons($subscription, Plan $newPlan): void
    {
        $activeAddons = $subscription->addons()->where('is_active', true)->get();

        foreach ($activeAddons as $addon) {
            $stillAvailable = match ($addon->addon_type) {
                'support' => $newPlan->support_addon_available,
                'delivery_app' => $newPlan->delivery_app_addon_available,
                default => false,
            };

            if (!$stillAvailable) {
                $addon->update(['is_active' => false]);
                Log::info('Addon deactivated due to plan change', [
                    'subscription_id' => $subscription->id,
                    'addon_type' => $addon->addon_type,
                    'new_plan' => $newPlan->name,
                ]);
            }
        }
    }

    private function calculateTotal($subscription, string $addonType, float $addonPrice, string $operation): float
    {
        $basePrice = (float) $subscription->price;

        // Sum currently active addon prices (excluding the one being toggled)
        $activeAddonTotal = $subscription->addons()
            ->where('is_active', true)
            ->where('addon_type', '!=', $addonType)
            ->sum('price');

        if ($operation === 'add') {
            return $basePrice + $activeAddonTotal + $addonPrice;
        }

        return $basePrice + $activeAddonTotal;
    }

    private function addonLabel(string $addonType): string
    {
        return match ($addonType) {
            'support' => 'Soporte Premium',
            'delivery_app' => 'App de Delivery',
            default => 'Addon',
        };
    }
}
