<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessWhatsAppMessage;
use App\Models\Tenant;
use App\Services\WhatsApp\WebhookPayloadParser;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $verifyToken = config('whatsapp.verify_token');
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token === $verifyToken) {
            Log::info('WhatsApp webhook verified');
            return response($challenge, 200);
        }

        return response('Forbidden', 403);
    }

    public function handle(Request $request): Response
    {
        $payload = $request->all();

        try {
            $messages = WebhookPayloadParser::extractMessages($payload);

            foreach ($messages as $message) {
                // Find tenant by phone_number_id
                $tenant = Tenant::where('whatsapp_phone_number_id', $message['phone_number_id'])
                    ->where('is_active', true)
                    ->first();

                if (!$tenant) {
                    Log::warning('WhatsApp webhook: Unknown phone_number_id', [
                        'phone_number_id' => $message['phone_number_id'],
                    ]);
                    continue;
                }

                // Dispatch async job
                ProcessWhatsAppMessage::dispatch(
                    $tenant->id,
                    $message['from'],
                    $message['type'],
                    $message['content'],
                    $message['message_id'],
                    $message['timestamp'],
                );
            }
        } catch (\Exception $e) {
            Log::error('WhatsApp webhook processing error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }

        // Always return 200 to prevent Meta from retrying
        return response('OK', 200);
    }
}
