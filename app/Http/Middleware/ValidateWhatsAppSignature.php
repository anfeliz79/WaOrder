<?php

namespace App\Http\Middleware;

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
        $appSecret = config('whatsapp.app_secret');

        if (!$signature || !$appSecret) {
            Log::warning('WhatsApp webhook: Missing signature or app secret');
            return response('Unauthorized', 401);
        }

        $payload = $request->getContent();
        $expectedSignature = 'sha256=' . hash_hmac('sha256', $payload, $appSecret);

        if (!hash_equals($expectedSignature, $signature)) {
            Log::warning('WhatsApp webhook: Invalid signature');
            return response('Unauthorized', 401);
        }

        return $next($request);
    }
}
