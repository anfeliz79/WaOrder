<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\CardnetPaymentSession;
use App\Services\Payment\CardnetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CardnetWebhookController extends Controller
{
    public function __construct(
        private CardnetService $cardnetService,
    ) {}

    /**
     * Handle Cardnet webhook notifications.
     * POST /webhook/cardnet
     */
    public function handle(Request $request)
    {
        Log::info('Cardnet webhook received', $request->all());

        $sessionId = $request->input('session_id') ?? $request->input('SESSION');
        $status = $request->input('status') ?? $request->input('Status');

        if (!$sessionId) {
            return response()->json(['message' => 'Missing session_id'], 400);
        }

        $session = CardnetPaymentSession::where('session_id', $sessionId)->first();
        if (!$session) {
            Log::warning('Cardnet webhook: Session not found', ['session_id' => $sessionId]);
            return response()->json(['message' => 'Session not found'], 404);
        }

        $normalizedStatus = strtolower($status ?? '');

        if (in_array($normalizedStatus, ['approved', 'completed', 'success'])) {
            $this->cardnetService->handlePaymentSuccess($session, $request->all());
        } elseif (in_array($normalizedStatus, ['rejected', 'failed', 'cancelled'])) {
            $this->cardnetService->handlePaymentCancel($session);
        }

        return response()->json(['message' => 'OK']);
    }
}
