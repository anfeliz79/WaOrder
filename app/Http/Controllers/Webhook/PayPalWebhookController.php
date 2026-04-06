<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Services\Payment\PayPalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PayPalWebhookController extends Controller
{
    public function handle(Request $request, PayPalService $paypal)
    {
        Log::info('PayPal webhook received', [
            'event_type' => $request->input('event_type'),
            'resource_id' => $request->input('resource.id'),
        ]);

        // Verify webhook signature
        if (! $paypal->verifyWebhook($request)) {
            Log::warning('PayPal webhook signature verification failed');

            return response('Invalid signature', 403);
        }

        $eventType = $request->input('event_type');
        $resource = $request->input('resource', []);

        return match ($eventType) {
            'BILLING.SUBSCRIPTION.ACTIVATED' => $this->handleActivated($resource),
            'BILLING.SUBSCRIPTION.CANCELLED' => $this->handleCancelled($resource),
            'BILLING.SUBSCRIPTION.SUSPENDED' => $this->handleSuspended($resource),
            'BILLING.SUBSCRIPTION.PAYMENT.FAILED' => $this->handlePaymentFailed($resource),
            'PAYMENT.SALE.COMPLETED' => $this->handlePaymentCompleted($resource),
            default => response('Event not handled', 200),
        };
    }

    private function findSubscription(array $resource): ?Subscription
    {
        $paypalSubId = $resource['id'] ?? null;
        if (! $paypalSubId) {
            return null;
        }

        return Subscription::withoutGlobalScope('tenant')
            ->where('paypal_subscription_id', $paypalSubId)
            ->first();
    }

    private function handleActivated(array $resource)
    {
        $subscription = $this->findSubscription($resource);
        if (! $subscription) {
            Log::warning('PayPal activated: subscription not found', $resource);

            return response('Not found', 200);
        }

        if (! in_array($subscription->status, ['active', 'trialing'])) {
            $subscription->update([
                'status' => 'active',
                'current_period_start' => now(),
                'current_period_end' => now()->addMonth(),
            ]);

            Log::info('PayPal subscription activated', ['subscription_id' => $subscription->id]);
        }

        return response('OK', 200);
    }

    private function handleCancelled(array $resource)
    {
        $subscription = $this->findSubscription($resource);
        if (! $subscription) {
            return response('Not found', 200);
        }

        $subscription->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'cancellation_reason' => 'Cancelado desde PayPal',
        ]);

        Log::info('PayPal subscription cancelled', ['subscription_id' => $subscription->id]);

        return response('OK', 200);
    }

    private function handleSuspended(array $resource)
    {
        $subscription = $this->findSubscription($resource);
        if (! $subscription) {
            return response('Not found', 200);
        }

        $subscription->update([
            'status' => 'past_due',
            'grace_period_ends_at' => now()->addDays(3),
        ]);

        Log::info('PayPal subscription suspended', ['subscription_id' => $subscription->id]);

        return response('OK', 200);
    }

    private function handlePaymentFailed(array $resource)
    {
        $paypalSubId = $resource['id'] ?? $resource['billing_agreement_id'] ?? null;
        $subscription = $paypalSubId
            ? Subscription::withoutGlobalScope('tenant')
                ->where('paypal_subscription_id', $paypalSubId)
                ->first()
            : null;

        if (! $subscription) {
            return response('Not found', 200);
        }

        $subscription->update([
            'status' => 'past_due',
            'grace_period_ends_at' => $subscription->grace_period_ends_at ?? now()->addDays(3),
        ]);

        Log::info('PayPal payment failed', ['subscription_id' => $subscription->id]);

        return response('OK', 200);
    }

    private function handlePaymentCompleted(array $resource)
    {
        $paypalSubId = $resource['billing_agreement_id'] ?? null;
        if (! $paypalSubId) {
            return response('No subscription ID', 200);
        }

        $subscription = Subscription::withoutGlobalScope('tenant')
            ->where('paypal_subscription_id', $paypalSubId)
            ->first();

        if (! $subscription) {
            return response('Not found', 200);
        }

        // Reactivate if past_due
        if ($subscription->status === 'past_due') {
            $subscription->update(['status' => 'active', 'grace_period_ends_at' => null]);
        }

        // Extend period
        $subscription->update([
            'current_period_start' => now(),
            'current_period_end' => $subscription->billing_period === 'annual'
                ? now()->addYear()
                : now()->addMonth(),
        ]);

        // Create invoice
        $amount = $resource['amount']['total'] ?? $subscription->price;

        Invoice::withoutGlobalScope('tenant')->create([
            'tenant_id' => $subscription->tenant_id,
            'subscription_id' => $subscription->id,
            'type' => 'subscription',
            'status' => 'paid',
            'amount' => $amount,
            'tax' => 0,
            'total' => $amount,
            'currency' => $resource['amount']['currency'] ?? 'USD',
            'payment_method' => 'paypal',
            'paid_at' => now(),
            'description' => 'Pago PayPal - ' . ($subscription->plan->name ?? 'Suscripcion'),
            'metadata' => ['paypal_sale_id' => $resource['id'] ?? null],
        ]);

        Log::info('PayPal payment completed', ['subscription_id' => $subscription->id]);

        return response('OK', 200);
    }
}
