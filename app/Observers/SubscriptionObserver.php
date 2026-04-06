<?php

namespace App\Observers;

use App\Mail\PlanActivatedMail;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SubscriptionObserver
{
    public function updated(Subscription $subscription): void
    {
        // Send plan activated email when status changes to active from pending_payment
        if (
            $subscription->wasChanged('status')
            && $subscription->status === 'active'
            && $subscription->getOriginal('status') === 'pending_payment'
        ) {
            $this->sendPlanActivatedEmail($subscription);
        }
    }

    private function sendPlanActivatedEmail(Subscription $subscription): void
    {
        $admin = User::withoutGlobalScope('tenant')
            ->where('tenant_id', $subscription->tenant_id)
            ->where('role', 'admin')
            ->first();

        if (!$admin) {
            return;
        }

        try {
            Mail::to($admin->email)->send(new PlanActivatedMail($admin, $subscription));
        } catch (\Throwable $e) {
            Log::warning('Plan activated email failed', [
                'email' => $admin->email,
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
