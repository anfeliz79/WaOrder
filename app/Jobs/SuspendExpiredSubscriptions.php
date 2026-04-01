<?php

namespace App\Jobs;

use App\Models\Subscription;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SuspendExpiredSubscriptions implements ShouldQueue
{
    use Queueable;

    public function handle(): void
    {
        $expired = Subscription::where('status', 'past_due')
            ->whereNotNull('grace_period_ends_at')
            ->where('grace_period_ends_at', '<', now())
            ->with('tenant')
            ->get();

        Log::info('SuspendExpiredSubscriptions: Found expired grace periods', [
            'count' => $expired->count(),
        ]);

        foreach ($expired as $subscription) {
            $subscription->update(['status' => 'suspended']);

            Log::warning('Subscription suspended after grace period', [
                'subscription_id' => $subscription->id,
                'tenant_id' => $subscription->tenant_id,
            ]);
        }
    }
}
