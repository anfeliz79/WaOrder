<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Services\Payment\PayPalService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class PayPalSubscriptionController extends Controller
{
    public function create(PayPalService $paypal)
    {
        $user = Auth::user();
        $subscription = Subscription::withoutGlobalScope('tenant')
            ->where('tenant_id', $user->tenant_id)
            ->where('status', 'pending_payment')
            ->first();

        if (! $subscription) {
            return response()->json(['error' => 'No hay suscripcion pendiente'], 400);
        }

        $plan = $subscription->plan;
        if (! $plan) {
            return response()->json(['error' => 'Plan no encontrado'], 400);
        }

        try {
            $result = $paypal->createSubscription(
                $plan,
                $subscription,
                url('/register/payment/paypal/callback'),
                url('/register/payment/paypal/cancel'),
            );

            $subscription->update([
                'payment_method' => 'paypal',
                'paypal_subscription_id' => $result['paypal_subscription_id'],
            ]);

            return response()->json([
                'approval_url' => $result['approval_url'],
            ]);
        } catch (\Exception $e) {
            Log::error('PayPal subscription creation failed', ['error' => $e->getMessage()]);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function callback(Request $request, PayPalService $paypal)
    {
        $paypalSubscriptionId = $request->query('subscription_id');

        if (! $paypalSubscriptionId) {
            return redirect('/register/payment')->with('error', 'Suscripcion de PayPal no encontrada');
        }

        $subscription = Subscription::withoutGlobalScope('tenant')
            ->where('paypal_subscription_id', $paypalSubscriptionId)
            ->first();

        if (! $subscription) {
            return redirect('/register/payment')->with('error', 'Suscripcion no encontrada');
        }

        try {
            $paypalSub = $paypal->getSubscription($paypalSubscriptionId);
            $status = $paypalSub['status'] ?? '';

            // Only ACTIVE means PayPal collected the first payment.
            // APPROVED means the user authorized but payment hasn't been collected yet.
            if ($status === 'ACTIVE') {
                $subscription->update([
                    'status' => 'active',
                    'current_period_start' => now(),
                    'current_period_end' => $subscription->billing_period === 'annual'
                        ? now()->addYear()
                        : now()->addMonth(),
                ]);

                // Create initial invoice
                $amount = $subscription->price;

                Invoice::withoutGlobalScope('tenant')->create([
                    'tenant_id' => $subscription->tenant_id,
                    'subscription_id' => $subscription->id,
                    'type' => 'subscription',
                    'status' => 'paid',
                    'amount' => $amount,
                    'tax' => 0,
                    'total' => $amount,
                    'currency' => $subscription->plan->currency ?? 'USD',
                    'payment_method' => 'paypal',
                    'paid_at' => now(),
                    'description' => "Pago inicial PayPal - {$subscription->plan->name}",
                    'metadata' => ['paypal_subscription_id' => $paypalSubscriptionId],
                ]);

                return redirect('/setup')->with('success', 'Pago con PayPal confirmado!');
            }

            if ($status === 'APPROVED') {
                Log::info('PayPal subscription approved but not yet active', [
                    'paypal_subscription_id' => $paypalSubscriptionId,
                    'tenant_id' => $subscription->tenant_id,
                ]);

                return redirect('/register/payment')->with('error', 'Tu suscripcion de PayPal fue autorizada pero el primer pago aun no se ha procesado. Intenta de nuevo en unos minutos.');
            }

            return redirect('/register/payment')->with('error', 'La suscripcion de PayPal no fue aprobada. Intenta de nuevo.');
        } catch (\Exception $e) {
            Log::error('PayPal callback failed', ['error' => $e->getMessage()]);

            return redirect('/register/payment')->with('error', 'Error verificando el pago con PayPal');
        }
    }

    public function cancel()
    {
        return redirect('/register/payment')->with('error', 'Pago con PayPal cancelado. Puedes elegir otro metodo de pago.');
    }
}
