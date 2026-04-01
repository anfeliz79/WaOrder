<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Services\Subscription\SubscriptionManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ConvertExpiredTrials implements ShouldQueue
{
    use Queueable;

    public function handle(SubscriptionManager $manager): void
    {
        $expiredTrials = Subscription::where('status', 'trialing')
            ->where('trial_ends_at', '<=', now())
            ->with(['tenant.defaultCardnetToken', 'plan'])
            ->get();

        Log::info('ConvertExpiredTrials: Found expired trials', [
            'count' => $expiredTrials->count(),
        ]);

        foreach ($expiredTrials as $subscription) {
            $tenant = $subscription->tenant;
            $token = $tenant->defaultCardnetToken;

            if ($subscription->plan->isFree()) {
                // Free plan — just activate
                $subscription->update([
                    'status' => 'active',
                    'current_period_start' => now(),
                    'current_period_end' => now()->addYear(),
                ]);
                continue;
            }

            if ($token) {
                $success = $manager->chargeSubscription($subscription, $token);
                if ($success) {
                    Log::info('ConvertExpiredTrials: Trial converted to active', [
                        'subscription_id' => $subscription->id,
                    ]);
                    continue;
                }
            }

            // No token or charge failed — enter grace period
            $manager->handlePaymentFailure($subscription);
            Log::warning('ConvertExpiredTrials: Trial conversion failed', [
                'subscription_id' => $subscription->id,
                'has_token' => (bool) $token,
            ]);
        }
    }
}
