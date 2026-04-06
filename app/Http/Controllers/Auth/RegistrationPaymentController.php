<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\CardnetToken;
use App\Models\PaymentMethod;
use App\Services\Payment\CardnetTokenizationService;
use App\Services\Subscription\SubscriptionManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;

class RegistrationPaymentController extends Controller
{
    public function __construct(
        private CardnetTokenizationService $tokenization,
        private SubscriptionManager $subscriptionManager,
    ) {}

    /**
     * Show the payment page for a pending_payment subscription.
     */
    public function show()
    {
        $tenant = app('tenant');
        $subscription = $tenant->subscription;

        if ($subscription?->isActive()) {
            return redirect('/setup');
        }

        if (!$subscription || $subscription->status !== 'pending_payment') {
            return redirect('/register');
        }

        // Create Cardnet customer on first visit
        $customerId = $tenant->getSetting('cardnet_customer_id');
        if (!$customerId) {
            $customerId = $this->tokenization->createCustomer($tenant);
            if ($customerId) {
                $settings = $tenant->settings ?? [];
                $settings['cardnet_customer_id'] = $customerId;
                $tenant->update(['settings' => $settings]);
            }
        }

        $env = config('cardnet.environment', 'testing');
        $checkoutScriptBase = $env === 'production'
            ? 'https://servicios.cardnet.com.do/servicios/tokens/v1'
            : 'https://labservicios.cardnet.com.do/servicios/tokens/v1';

        $bankAccounts = BankAccount::where('is_active', true)
            ->orderBy('created_at')
            ->get(['id', 'bank_name', 'account_holder_name', 'account_number', 'account_type', 'currency', 'instructions']);

        // Load active payment methods configured by SuperAdmin
        $paymentMethods = [];
        try {
            $paymentMethods = PaymentMethod::active()->get()->map(fn($m) => [
                'slug' => $m->slug,
                'name' => $m->name,
                'icon' => $m->icon,
                'description' => $m->description,
            ])->values()->toArray();
        } catch (\Throwable $e) {
            // PaymentMethod table may not exist yet — fall back to empty (legacy mode)
            Log::debug('PaymentMethod query failed, using legacy payment tabs', ['error' => $e->getMessage()]);
        }

        return Inertia::render('Auth/RegisterPayment', [
            'plan'               => $subscription->load('plan')->plan,
            'publicKey'          => config('cardnet.platform.public_key'),
            'checkoutScriptBase' => $checkoutScriptBase,
            'bankAccounts'       => $bankAccounts,
            'payment_methods'    => $paymentMethods,
        ]);
    }

    /**
     * Receive token from Cardnet PWCheckout.js and charge first month.
     */
    public function tokenize(Request $request)
    {
        $request->validate([
            'token_id'     => 'required|string',
            'brand'        => 'nullable|string|max:30',
            'last4'        => 'nullable|string|max:4',
            'expiry_month' => 'nullable|string|max:2',
            'expiry_year'  => 'nullable|string|max:4',
        ]);

        $tenant = app('tenant');
        $subscription = $tenant->subscription;

        if (!$subscription || $subscription->status !== 'pending_payment') {
            return response()->json([
                'success' => false,
                'message' => 'No hay suscripción pendiente de pago.',
            ], 422);
        }

        // Deactivate any prior tokens
        CardnetToken::where('tenant_id', $tenant->id)
            ->update(['is_default' => false, 'is_active' => false]);

        $expiry = null;
        if ($request->expiry_month && $request->expiry_year) {
            $expiry = $request->expiry_month . '/' . substr($request->expiry_year, -2);
        }

        $token = CardnetToken::create([
            'tenant_id'           => $tenant->id,
            'cardnet_customer_id' => $tenant->getSetting('cardnet_customer_id', ''),
            'trx_token'           => $request->token_id,
            'card_brand'          => $request->brand,
            'card_last_four'      => $request->last4,
            'card_expiry'         => $expiry,
            'is_default'          => true,
            'is_active'           => true,
        ]);

        $charged = $this->subscriptionManager->chargeSubscription($subscription, $token);

        if ($charged) {
            Log::info('Registration payment successful', ['tenant_id' => $tenant->id]);
            return response()->json(['success' => true, 'redirect' => '/setup']);
        }

        // Charge failed — remove token so user can retry
        $token->delete();

        Log::warning('Registration payment failed', ['tenant_id' => $tenant->id]);

        return response()->json([
            'success' => false,
            'message' => 'El pago fue rechazado. Verifica los datos de tu tarjeta e intenta de nuevo.',
        ], 422);
    }
}
