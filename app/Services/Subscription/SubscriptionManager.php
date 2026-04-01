<?php

namespace App\Services\Subscription;

use App\Models\CardnetToken;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\Tenant;
use App\Services\Payment\CardnetTokenizationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SubscriptionManager
{
    public function __construct(
        private CardnetTokenizationService $tokenization,
    ) {}

    public function subscribe(Tenant $tenant, Plan $plan, string $billingPeriod = 'monthly', ?CardnetToken $token = null): Subscription
    {
        return DB::transaction(function () use ($tenant, $plan, $billingPeriod, $token) {
            // Cancel any existing subscription
            $existing = $tenant->subscription;
            if ($existing && $existing->isActive()) {
                $existing->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancellation_reason' => 'Switched to new plan',
                ]);
            }

            $price = $plan->getPriceForPeriod($billingPeriod);

            $subscription = Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => 'active',
                'billing_period' => $billingPeriod,
                'price' => $price,
                'current_period_start' => now(),
                'current_period_end' => $billingPeriod === 'annual' ? now()->addYear() : now()->addMonth(),
            ]);

            $tenant->update(['plan_id' => $plan->id, 'subscription_plan' => $plan->slug]);

            // Charge immediately if paid plan with token
            if ($price > 0 && $token) {
                $this->chargeSubscription($subscription, $token);
            }

            return $subscription;
        });
    }

    public function startTrial(Tenant $tenant, Plan $plan, ?int $trialDays = null): Subscription
    {
        $days = $trialDays ?? $plan->trial_days;

        return DB::transaction(function () use ($tenant, $plan, $days) {
            $subscription = Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => 'trialing',
                'billing_period' => 'monthly',
                'price' => $plan->price_monthly,
                'trial_ends_at' => now()->addDays($days),
                'current_period_start' => now(),
                'current_period_end' => now()->addDays($days),
            ]);

            $tenant->update(['plan_id' => $plan->id, 'subscription_plan' => $plan->slug]);

            return $subscription;
        });
    }

    public function changePlan(Subscription $subscription, Plan $newPlan): Subscription
    {
        return DB::transaction(function () use ($subscription, $newPlan) {
            $price = $newPlan->getPriceForPeriod($subscription->billing_period);

            $subscription->update([
                'plan_id' => $newPlan->id,
                'price' => $price,
            ]);

            $subscription->tenant->update([
                'plan_id' => $newPlan->id,
                'subscription_plan' => $newPlan->slug,
            ]);

            return $subscription->fresh();
        });
    }

    public function cancel(Subscription $subscription, string $reason = ''): void
    {
        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => $reason,
        ]);
    }

    public function reactivate(Subscription $subscription): void
    {
        if (!$subscription->isCancelled()) {
            return;
        }

        $subscription->update([
            'status' => 'active',
            'cancelled_at' => null,
            'cancellation_reason' => null,
            'current_period_start' => now(),
            'current_period_end' => $subscription->billing_period === 'annual'
                ? now()->addYear()
                : now()->addMonth(),
        ]);
    }

    public function chargeSubscription(Subscription $subscription, ?CardnetToken $token = null): bool
    {
        $tenant = $subscription->tenant;
        $token = $token ?? $tenant->defaultCardnetToken;

        if (!$token) {
            Log::warning('SubscriptionManager: No payment token for tenant', ['tenant_id' => $tenant->id]);
            return false;
        }

        $orderNumber = 'SUB-' . $tenant->id . '-' . now()->format('Ymd') . '-' . Str::random(4);

        // Create invoice
        $invoice = Invoice::create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'type' => 'subscription',
            'status' => 'pending',
            'amount' => $subscription->price,
            'tax' => 0,
            'total' => $subscription->price,
            'currency' => $subscription->plan->currency ?? 'DOP',
            'description' => "Suscripcion {$subscription->plan->name} - {$subscription->billing_period}",
            'due_at' => now(),
        ]);

        // Charge via Cardnet
        $result = $this->tokenization->createPurchase(
            $token,
            (float) $subscription->price,
            $orderNumber,
            $subscription->plan->currency ?? 'DOP'
        );

        if ($result['success']) {
            $invoice->markAsPaid(
                $result['purchase_id'] ?? null,
                $result['data'] ?? null
            );
            $this->handlePaymentSuccess($subscription);
            return true;
        }

        $invoice->markAsFailed($result['data'] ?? null);
        $this->handlePaymentFailure($subscription);
        return false;
    }

    public function handlePaymentSuccess(Subscription $subscription): void
    {
        $nextEnd = $subscription->billing_period === 'annual'
            ? now()->addYear()
            : now()->addMonth();

        $subscription->update([
            'status' => 'active',
            'current_period_start' => now(),
            'current_period_end' => $nextEnd,
            'grace_period_ends_at' => null,
        ]);
    }

    public function handlePaymentFailure(Subscription $subscription): void
    {
        $graceDays = config('cardnet.subscription.grace_period_days', 3);

        $subscription->update([
            'status' => 'past_due',
            'grace_period_ends_at' => now()->addDays($graceDays),
        ]);

        Log::warning('Subscription payment failed', [
            'subscription_id' => $subscription->id,
            'tenant_id' => $subscription->tenant_id,
            'grace_until' => $subscription->grace_period_ends_at,
        ]);
    }
}
