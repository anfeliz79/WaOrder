<?php

namespace App\Services\Payment;

use App\Models\CardnetPaymentSession;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CardnetService
{
    /**
     * Create a payment session for a customer order.
     * Uses the TENANT's Cardnet credentials (not platform).
     */
    public function createPaymentSession(Order $order, Tenant $tenant): ?CardnetPaymentSession
    {
        $credentials = $this->getTenantCredentials($tenant);
        if (!$credentials) {
            Log::error('CardnetService: Tenant has no Cardnet credentials', ['tenant_id' => $tenant->id]);
            return null;
        }

        // Create the session record first
        $session = CardnetPaymentSession::create([
            'tenant_id' => $tenant->id,
            'order_id' => $order->id,
            'amount' => $order->total,
            'currency' => $tenant->currency ?? 'DOP',
            'status' => 'pending',
            'expires_at' => now()->addMinutes(30),
        ]);

        $baseUrl = $this->getPaymentBaseUrl();
        $returnUrl = url("/pay/{$session->uuid}/success");
        $cancelUrl = url("/pay/{$session->uuid}/cancel");

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
            ])->post("{$baseUrl}/api/sessions", [
                'merchant_id' => $credentials['merchant_id'],
                'terminal_id' => $credentials['terminal_id'],
                'amount' => (int) round($order->total * 100), // cents
                'currency' => $this->getCurrencyCode($tenant->currency ?? 'DOP'),
                'order_number' => $order->order_number,
                'return_url' => $returnUrl,
                'cancel_url' => $cancelUrl,
                'description' => "Pedido #{$order->order_number} - {$tenant->name}",
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $session->update([
                    'session_id' => $data['session_id'] ?? $data['SESSION'] ?? null,
                    'session_key' => $data['session_key'] ?? $data['key'] ?? null,
                    'cardnet_response' => $data,
                ]);

                return $session;
            }

            Log::error('CardnetService: Failed to create session', [
                'status' => $response->status(),
                'body' => $response->body(),
                'order_id' => $order->id,
            ]);

            $session->update([
                'status' => 'rejected',
                'cardnet_response' => ['error' => $response->body()],
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('CardnetService: Exception creating session', [
                'error' => $e->getMessage(),
                'order_id' => $order->id,
            ]);

            $session->update([
                'status' => 'rejected',
                'cardnet_response' => ['error' => $e->getMessage()],
            ]);

            return null;
        }
    }

    /**
     * Query the result of a payment session.
     */
    public function querySessionResult(CardnetPaymentSession $session): array
    {
        if (!$session->session_id) {
            return ['status' => 'error', 'message' => 'No session ID'];
        }

        $tenant = $session->tenant;
        $credentials = $this->getTenantCredentials($tenant);
        if (!$credentials) {
            return ['status' => 'error', 'message' => 'No credentials'];
        }

        $baseUrl = $this->getPaymentBaseUrl();

        try {
            $response = Http::get("{$baseUrl}/api/sessions/{$session->session_id}", [
                'key' => $session->session_key,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return [
                    'status' => $data['status'] ?? 'unknown',
                    'data' => $data,
                ];
            }

            return ['status' => 'error', 'message' => $response->body()];
        } catch (\Exception $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Process a successful payment callback.
     */
    public function handlePaymentSuccess(CardnetPaymentSession $session, array $callbackData = []): bool
    {
        if ($session->status !== 'pending') {
            return false;
        }

        $session->update([
            'status' => 'approved',
            'cardnet_response' => array_merge($session->cardnet_response ?? [], $callbackData),
        ]);

        // Create or update Payment record
        Payment::updateOrCreate(
            ['order_id' => $session->order_id, 'tenant_id' => $session->tenant_id],
            [
                'method' => 'card',
                'status' => 'confirmed',
                'amount' => $session->amount,
                'reference' => $callbackData['transaction_id'] ?? $session->session_id,
                'gateway_response' => $callbackData,
                'gateway' => 'cardnet',
                'cardnet_session_id' => $session->session_id,
                'confirmed_at' => now(),
            ]
        );

        return true;
    }

    /**
     * Handle a cancelled/failed payment.
     */
    public function handlePaymentCancel(CardnetPaymentSession $session): void
    {
        if ($session->status !== 'pending') {
            return;
        }

        $session->update(['status' => 'rejected']);

        Payment::updateOrCreate(
            ['order_id' => $session->order_id, 'tenant_id' => $session->tenant_id],
            [
                'method' => 'card',
                'status' => 'failed',
                'amount' => $session->amount,
                'gateway' => 'cardnet',
                'cardnet_session_id' => $session->session_id,
            ]
        );
    }

    private function getTenantCredentials(Tenant $tenant): ?array
    {
        $cardnet = $tenant->getSetting('payment.cardnet');
        if (!$cardnet || empty($cardnet['merchant_id']) || empty($cardnet['terminal_id'])) {
            return null;
        }

        return $cardnet;
    }

    private function getPaymentBaseUrl(): string
    {
        $env = config('cardnet.environment', 'testing');
        return config("cardnet.urls.{$env}.payment_base");
    }

    private function getCurrencyCode(string $currency): string
    {
        return match ($currency) {
            'DOP' => '214',
            'USD' => '840',
            default => '214',
        };
    }
}
