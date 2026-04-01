<?php

namespace App\Jobs;

use App\Models\CardnetPaymentSession;
use App\Services\Payment\CardnetService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CheckCardnetPaymentStatus implements ShouldQueue
{
    use Queueable;

    public function handle(CardnetService $cardnetService): void
    {
        // Mark expired sessions
        CardnetPaymentSession::where('status', 'pending')
            ->where('expires_at', '<', now())
            ->update(['status' => 'expired']);

        // Check pending sessions that are within the 30-min window
        $pendingSessions = CardnetPaymentSession::where('status', 'pending')
            ->where('created_at', '>', now()->subMinutes(30))
            ->where('session_id', '!=', null)
            ->get();

        foreach ($pendingSessions as $session) {
            $result = $cardnetService->querySessionResult($session);

            $status = strtolower($result['status'] ?? 'unknown');

            if (in_array($status, ['approved', 'completed'])) {
                $cardnetService->handlePaymentSuccess($session, $result['data'] ?? []);
                Log::info('CheckCardnetPayment: Payment approved', ['session_id' => $session->session_id]);
            } elseif (in_array($status, ['rejected', 'failed'])) {
                $cardnetService->handlePaymentCancel($session);
                Log::info('CheckCardnetPayment: Payment rejected', ['session_id' => $session->session_id]);
            }
        }
    }
}
