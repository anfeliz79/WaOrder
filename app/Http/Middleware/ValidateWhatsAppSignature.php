<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ValidateWhatsAppSignature
{
    public function handle(Request $request, Closure $next): Response
    {
        // Skip validation for GET (webhook verification)
        if ($request->isMethod('GET')) {
            return $next($request);
        }

        $signature = $request->header('X-Hub-Signature-256');

        if (!$signature) {
            Log::warning('WhatsApp webhook: Missing signature header');
            return response('Unauthorized', 401);
        }

        $payload = $request->getContent();

        // Try to resolve app_secret from the tenant (by phone_number_id in payload)
        $appSecret = $this->resolveAppSecret($payload);

        if (!$appSecret) {
            Log::warning('WhatsApp webhook: No app secret configured (neither tenant nor .env)');
            return response('Unauthorized', 401);
        }

        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $appSecret);

        if (!hash_equals($expectedSignature, $signature)) {
            Log::warning('WhatsApp webhook: Invalid signature');
            return response('Unauthorized', 401);
        }

        return $next($request);
    }

    /**
     * Resolve app_secret: first try tenant-level (from DB), then fall back to .env global.
     */
    private function resolveAppSecret(string $payload): ?string
    {
        // Try to extract phone_number_id from the webhook payload
        $data = json_decode($payload, true);
        $phoneNumberId = $data['entry'][0]['changes'][0]['value']['metadata']['phone_number_id'] ?? null;

        if ($phoneNumberId) {
            $tenant = Tenant::withoutGlobalScopes()
                ->where('whatsapp_phone_number_id', $phoneNumberId)
                ->where('is_active', true)
                ->first();

            if ($tenant && $tenant->whatsapp_app_secret) {
                return $tenant->whatsapp_app_secret;
            }
        }

        // Fall back to global .env config
        $envSecret = config('whatsapp.app_secret');

        return ($envSecret && $envSecret !== 'TU_APP_SECRET_DE_META') ? $envSecret : null;
    }
}
