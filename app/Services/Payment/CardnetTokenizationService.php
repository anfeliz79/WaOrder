<?php

namespace App\Services\Payment;

use App\Models\CardnetToken;
use App\Models\Tenant;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class CardnetTokenizationService
{
    /**
     * Create a customer in Cardnet for card tokenization.
     * Uses PLATFORM credentials for subscription billing.
     */
    public function createCustomer(Tenant $tenant): ?string
    {
        $baseUrl = $this->getBaseUrl();
        $credentials = $this->getPlatformCredentials();

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($credentials['private_key'] . ':'),
                'Content-Type' => 'application/json',
            ])->post("{$baseUrl}/customer", [
                'Email' => $tenant->users()->where('role', 'admin')->first()?->email,
                'FirstName' => $tenant->name,
                'DocumentTypeId' => 24, // RNC/Cedula
                'DocNumber' => $tenant->getSetting('rnc', '000000000'),
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['CustomerId'] ?? null;
            }

            Log::error('CardnetTokenization: Failed to create customer', [
                'status' => $response->status(),
                'body' => $response->body(),
                'tenant_id' => $tenant->id,
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('CardnetTokenization: Exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Get the card capture URL for a customer.
     */
    public function getCaptureUrl(string $customerId): ?string
    {
        $baseUrl = $this->getBaseUrl();
        $credentials = $this->getPlatformCredentials();

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($credentials['private_key'] . ':'),
            ])->get("{$baseUrl}/customer/{$customerId}");

            if ($response->successful()) {
                $data = $response->json();
                return $data['CaptureURL'] ?? null;
            }

            return null;
        } catch (\Exception $e) {
            Log::error('CardnetTokenization: getCaptureUrl error', ['error' => $e->getMessage()]);
            return null;
        }
    }

    /**
     * Create a purchase using a stored token (for recurring billing).
     */
    public function createPurchase(CardnetToken $token, float $amount, string $orderNumber, string $currency = 'DOP'): array
    {
        $baseUrl = $this->getBaseUrl();
        $credentials = $this->getPlatformCredentials();

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Basic ' . base64_encode($credentials['private_key'] . ':'),
                'Content-Type' => 'application/json',
            ])->post("{$baseUrl}/purchase", [
                'TrxToken' => $token->trx_token,
                'Order' => $orderNumber,
                'Amount' => (int) round($amount * 100),
                'Currency' => $this->getCurrencyCode($currency),
                'Capture' => true,
                'Description' => "Suscripcion WaOrder - {$orderNumber}",
            ]);

            $data = $response->json();

            if ($response->successful() && ($data['Status'] ?? '') === 'Approved') {
                return [
                    'success' => true,
                    'purchase_id' => $data['PurchaseId'] ?? null,
                    'data' => $data,
                ];
            }

            return [
                'success' => false,
                'error' => $data['ErrorDetail'] ?? $response->body(),
                'data' => $data,
            ];
        } catch (\Exception $e) {
            Log::error('CardnetTokenization: Purchase failed', ['error' => $e->getMessage()]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'data' => [],
            ];
        }
    }

    private function getPlatformCredentials(): array
    {
        return [
            'public_key' => config('cardnet.platform.public_key'),
            'private_key' => config('cardnet.platform.private_key'),
        ];
    }

    private function getBaseUrl(): string
    {
        $env = config('cardnet.environment', 'testing');
        return config("cardnet.urls.{$env}.tokenization_base");
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
