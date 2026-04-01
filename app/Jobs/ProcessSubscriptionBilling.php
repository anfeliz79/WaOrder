<?php

namespace App\Jobs;

use App\Models\Subscription;
use App\Services\Subscription\SubscriptionManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessSubscriptionBilling implements ShouldQueue
{
    use Queueable;

    public function handle(SubscriptionManager $manager): void
    {
        // Find active subscriptions whose period has ended
        $dueSubscriptions = Subscription::where('status', 'active')
            ->where('current_period_end', '<=', now())
            ->with(['tenant.defaultCardnetToken', 'plan'])
            ->get();

        Log::info('ProcessSubscriptionBilling: Found subscriptions due', [
            'count' => $dueSubscriptions->count(),
        ]);

        foreach ($dueSubscriptions as $subscription) {
            $success = $manager->chargeSubscription($subscription);

            Log::info('ProcessSubscriptionBilling: Charged subscription', [
                'subscription_id' => $subscription->id,
                'tenant_id' => $subscription->tenant_id,
                'success' => $success,
            ]);
        }
    }
}
