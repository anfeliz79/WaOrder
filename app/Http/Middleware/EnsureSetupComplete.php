<?php

namespace App\Http\Middleware;

use App\Models\Subscription;
use App\Models\TransferVerification;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        // Skip for these routes — they must remain accessible during onboarding/payment
        if ($request->is('setup*') || $request->is('register/*') || $request->is('logout') || $request->is('api/*') || $request->is('login') || $request->is('superadmin*') || $request->is('console*')) {
            return $next($request);
        }

        // SuperAdmin bypasses all checks
        if ($request->user()?->isSuperAdmin()) {
            return $next($request);
        }

        $tenant = app()->bound('tenant') ? app('tenant') : null;

        if ($tenant) {
            // Block access if subscription is still pending payment
            $subscription = Subscription::withoutGlobalScope('tenant')
                ->where('tenant_id', $tenant->id)
                ->latest()
                ->first();

            if ($subscription && $subscription->status === 'pending_payment') {
                // Bank transfer: check both payment_method field AND existing TransferVerification
                // (covers tenants who submitted before payment_method was saved)
                $hasPendingTransfer = $subscription->payment_method === 'bank_transfer'
                    || TransferVerification::withoutGlobalScope('tenant')
                        ->where('subscription_id', $subscription->id)
                        ->whereIn('status', ['pending', 'rejected'])
                        ->exists();

                if ($hasPendingTransfer) {
                    return redirect('/register/bank-transfer/pending');
                }
                // Everyone else goes to the payment page
                return redirect('/register/payment');
            }

            $setupCompleted = data_get($tenant->settings, 'setup_completed', false);

            if (!$setupCompleted) {
                return redirect('/setup');
            }
        }

        return $next($request);
    }
}
