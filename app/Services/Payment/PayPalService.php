<?php

namespace App\Services\Payment;

use App\Models\PaymentMethod;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalService
{
    private ?array $config = null;

    private function getConfig(): array
    {
        if ($this->config) {
            return $this->config;
        }

        // Try PaymentMethod model first, fallback to config file
        $method = PaymentMethod::where('slug', 'paypal')->first();
        if ($method && !empty($method->config['client_id'])) {
            $this->config = $method->config;
        } else {
            $this->config = config('paypal', []);
        }

        if (empty($this->config['client_id']) || empty($this->config['client_secret'])) {
            throw new \Exception('PayPal no esta configurado');
        }

        return $this->config;
    }

    private function getBaseUrl(): string
    {
        $config = $this->getConfig();

        return ($config['mode'] ?? 'sandbox') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    private function getAccessToken(): string
    {
        return Cache::remember('paypal_access_token', 3500, function () {
            $config = $this->getConfig();
            $response = Http::asForm()
                ->withBasicAuth($config['client_id'], $config['client_secret'])
                ->post("{$this->getBaseUrl()}/v1/oauth2/token", [
                    'grant_type' => 'client_credentials',
                ]);

            if (! $response->successful()) {
                Log::error('PayPal auth failed', ['response' => $response->body()]);
                throw new \Exception('Error de autenticacion con PayPal');
            }

            return $response->json('access_token');
        });
    }

    private function api(): \Illuminate\Http\Client\PendingRequest
    {
        return Http::withToken($this->getAccessToken())
            ->baseUrl($this->getBaseUrl())
            ->acceptJson();
    }

    /**
     * Create a PayPal Product (one-time, represents WaOrder platform).
     */
    public function ensureProduct(): string
    {
        return Cache::remember('paypal_product_id', 86400, function () {
            // Check if product already exists in config
            $config = $this->getConfig();
            if (! empty($config['product_id'])) {
                return $config['product_id'];
            }

            $response = $this->api()->post('/v1/catalogs/products', [
                'name' => 'WaOrder Subscription',
                'description' => 'Suscripcion a la plataforma WaOrder',
                'type' => 'SERVICE',
                'category' => 'SOFTWARE',
            ]);

            if (! $response->successful()) {
                Log::error('PayPal create product failed', ['response' => $response->body()]);
                throw new \Exception('Error creando producto en PayPal');
            }

            $productId = $response->json('id');

            Log::info('PayPal product created', ['product_id' => $productId]);
            Log::warning("Add PAYPAL_PRODUCT_ID={$productId} to your .env file to avoid re-creating the product.");

            return $productId;
        });
    }

    /**
     * Create or get PayPal Plan for a WaOrder Plan.
     */
    public function ensurePlan(Plan $plan, string $billingPeriod = 'monthly'): string
    {
        // Check if plan already has a PayPal plan ID
        if ($plan->paypal_plan_id) {
            return $plan->paypal_plan_id;
        }

        $productId = $this->ensureProduct();
        $price = $billingPeriod === 'annual' ? $plan->price_annual : $plan->price_monthly;

        $response = $this->api()->post('/v1/billing/plans', [
            'product_id' => $productId,
            'name' => "WaOrder - {$plan->name} ({$billingPeriod})",
            'description' => $plan->description ?? "Plan {$plan->name}",
            'billing_cycles' => [
                [
                    'frequency' => [
                        'interval_unit' => $billingPeriod === 'annual' ? 'YEAR' : 'MONTH',
                        'interval_count' => 1,
                    ],
                    'tenure_type' => 'REGULAR',
                    'sequence' => 1,
                    'total_cycles' => 0, // infinite
                    'pricing_scheme' => [
                        'fixed_price' => [
                            'value' => number_format($price, 2, '.', ''),
                            'currency_code' => $plan->currency ?? 'USD',
                        ],
                    ],
                ],
            ],
            'payment_preferences' => [
                'auto_bill_outstanding' => true,
                'setup_fee_failure_action' => 'CANCEL',
                'payment_failure_threshold' => 3,
            ],
        ]);

        if (! $response->successful()) {
            Log::error('PayPal create plan failed', ['response' => $response->body()]);
            throw new \Exception('Error creando plan en PayPal');
        }

        $paypalPlanId = $response->json('id');
        $plan->update(['paypal_plan_id' => $paypalPlanId]);

        return $paypalPlanId;
    }

    /**
     * Create a PayPal Subscription and return the approval URL.
     */
    public function createSubscription(Plan $plan, Subscription $subscription, string $returnUrl, string $cancelUrl): array
    {
        $paypalPlanId = $this->ensurePlan($plan, $subscription->billing_period ?? 'monthly');

        $response = $this->api()->post('/v1/billing/subscriptions', [
            'plan_id' => $paypalPlanId,
            'application_context' => [
                'brand_name' => 'WaOrder',
                'locale' => 'es-DO',
                'shipping_preference' => 'NO_SHIPPING',
                'user_action' => 'SUBSCRIBE_NOW',
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ],
            'custom_id' => (string) $subscription->id,
        ]);

        if (! $response->successful()) {
            Log::error('PayPal create subscription failed', [
                'response' => $response->body(),
                'subscription_id' => $subscription->id,
            ]);
            throw new \Exception('Error creando suscripcion en PayPal');
        }

        $data = $response->json();
        $approvalUrl = collect($data['links'])->firstWhere('rel', 'approve')['href'] ?? null;

        return [
            'paypal_subscription_id' => $data['id'],
            'approval_url' => $approvalUrl,
        ];
    }

    /**
     * Get subscription details from PayPal.
     */
    public function getSubscription(string $paypalSubscriptionId): array
    {
        $response = $this->api()->get("/v1/billing/subscriptions/{$paypalSubscriptionId}");

        if (! $response->successful()) {
            Log::error('PayPal get subscription failed', ['id' => $paypalSubscriptionId]);
            throw new \Exception('Error consultando suscripcion en PayPal');
        }

        return $response->json();
    }

    /**
     * Revise a PayPal subscription (change billing amount for addons).
     *
     * If the new price is higher, PayPal returns an approval URL.
     * If lower or equal, the change applies immediately.
     */
    public function reviseSubscription(
        string $paypalSubscriptionId,
        float $newPrice,
        string $currency = 'USD',
        string $billingPeriod = 'monthly',
        string $returnUrl = '',
        string $cancelUrl = '',
    ): array {
        $response = $this->api()->post("/v1/billing/subscriptions/{$paypalSubscriptionId}/revise", [
            'plan' => [
                'billing_cycles' => [
                    [
                        'frequency' => [
                            'interval_unit' => $billingPeriod === 'annual' ? 'YEAR' : 'MONTH',
                            'interval_count' => 1,
                        ],
                        'tenure_type' => 'REGULAR',
                        'sequence' => 1,
                        'total_cycles' => 0,
                        'pricing_scheme' => [
                            'fixed_price' => [
                                'value' => number_format($newPrice, 2, '.', ''),
                                'currency_code' => $currency,
                            ],
                        ],
                    ],
                ],
            ],
            'application_context' => [
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
            ],
        ]);

        if (! $response->successful()) {
            Log::error('PayPal revise subscription failed', [
                'response' => $response->body(),
                'subscription_id' => $paypalSubscriptionId,
                'new_price' => $newPrice,
            ]);
            throw new \Exception('Error revisando suscripcion en PayPal');
        }

        $data = $response->json();
        $approvalUrl = collect($data['links'] ?? [])->firstWhere('rel', 'approve')['href'] ?? null;

        return [
            'requires_approval' => $approvalUrl !== null,
            'approval_url' => $approvalUrl,
        ];
    }

    /**
     * Cancel a PayPal subscription.
     */
    public function cancelSubscription(string $paypalSubscriptionId, string $reason = 'Cancelled by user'): bool
    {
        $response = $this->api()->post("/v1/billing/subscriptions/{$paypalSubscriptionId}/cancel", [
            'reason' => $reason,
        ]);

        return $response->status() === 204;
    }

    /**
     * Verify webhook signature.
     */
    public function verifyWebhook(Request $request): bool
    {
        $config = $this->getConfig();
        $webhookId = $config['webhook_id'] ?? null;

        if (! $webhookId) {
            Log::warning('PayPal webhook_id not configured, skipping verification');

            return true; // Allow in development
        }

        $response = $this->api()->post('/v1/notifications/verify-webhook-signature', [
            'auth_algo' => $request->header('PAYPAL-AUTH-ALGO'),
            'cert_url' => $request->header('PAYPAL-CERT-URL'),
            'transmission_id' => $request->header('PAYPAL-TRANSMISSION-ID'),
            'transmission_sig' => $request->header('PAYPAL-TRANSMISSION-SIG'),
            'transmission_time' => $request->header('PAYPAL-TRANSMISSION-TIME'),
            'webhook_id' => $webhookId,
            'webhook_event' => $request->all(),
        ]);

        return $response->json('verification_status') === 'SUCCESS';
    }
}
