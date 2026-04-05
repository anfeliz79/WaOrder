<?php

namespace App\Services\Subscription;

use App\Mail\TransferRejectedMail;
use App\Mail\TransferVerifiedMail;
use App\Models\BankAccount;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TransferVerification;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BankTransferService
{
    /**
     * Store transfer evidence and create a pending verification record.
     */
    public function submitEvidence(
        Tenant $tenant,
        Subscription $subscription,
        BankAccount $bankAccount,
        float $amount,
        ?string $referenceNumber,
        UploadedFile $file
    ): TransferVerification {
        // Store file under a tenant-scoped directory
        $path = $file->store("transfer-verifications/{$tenant->id}", 'public');

        $verification = TransferVerification::create([
            'tenant_id'        => $tenant->id,
            'subscription_id'  => $subscription->id,
            'bank_account_id'  => $bankAccount->id,
            'amount'           => $amount,
            'reference_number' => $referenceNumber,
            'evidence_path'    => $path,
            'evidence_name'    => $file->getClientOriginalName(),
            'status'           => 'pending',
            'deadline_at'      => now()->addHours(12),
        ]);

        Cache::forget('superadmin_pending_transfers_count');

        Log::info('Transfer verification submitted', [
            'tenant_id'       => $tenant->id,
            'verification_id' => $verification->id,
            'amount'          => $amount,
        ]);

        return $verification;
    }

    /**
     * Approve a transfer: activate the subscription and notify the tenant admin.
     */
    public function approve(TransferVerification $verification, User $admin, ?string $notes = null): void
    {
        $verification->update([
            'status'      => 'approved',
            'admin_notes' => $notes,
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);

        $subscription = $verification->subscription;

        // Activate subscription for one billing period
        $subscription->update([
            'status'               => 'active',
            'current_period_start' => now(),
            'current_period_end'   => now()->addMonth(),
        ]);

        // Create a paid invoice for this transfer
        Invoice::create([
            'tenant_id'       => $verification->tenant_id,
            'subscription_id' => $subscription->id,
            'type'            => 'subscription',
            'status'          => 'paid',
            'amount'          => $verification->amount,
            'tax'             => 0,
            'total'           => $verification->amount,
            'currency'        => $verification->bankAccount->currency ?? 'DOP',
            'description'     => "Pago por transferencia bancaria — {$subscription->plan->name}",
            'paid_at'         => now(),
        ]);

        Cache::forget('superadmin_pending_transfers_count');

        Log::info('Transfer verification approved', [
            'verification_id' => $verification->id,
            'approved_by'     => $admin->id,
        ]);

        $this->notifyTenantAdmin($verification, 'approved');
    }

    /**
     * Reject a transfer: keep subscription pending and notify the tenant admin.
     */
    public function reject(TransferVerification $verification, User $admin, ?string $notes = null): void
    {
        $verification->update([
            'status'      => 'rejected',
            'admin_notes' => $notes,
            'verified_by' => $admin->id,
            'verified_at' => now(),
        ]);

        Cache::forget('superadmin_pending_transfers_count');

        Log::info('Transfer verification rejected', [
            'verification_id' => $verification->id,
            'rejected_by'     => $admin->id,
        ]);

        $this->notifyTenantAdmin($verification, 'rejected');
    }

    /**
     * Expire a pending verification that passed the 12-hour deadline without action.
     */
    public function expire(TransferVerification $verification): void
    {
        $verification->update(['status' => 'expired']);

        Log::info('Transfer verification expired', ['verification_id' => $verification->id]);
    }

    private function notifyTenantAdmin(TransferVerification $verification, string $action): void
    {
        $tenantAdmin = User::withoutGlobalScope('tenant')
            ->where('tenant_id', $verification->tenant_id)
            ->where('role', 'admin')
            ->first();

        if (!$tenantAdmin) {
            return;
        }

        try {
            if ($action === 'approved') {
                Mail::to($tenantAdmin->email)->send(new TransferVerifiedMail($verification));
            } else {
                Mail::to($tenantAdmin->email)->send(new TransferRejectedMail($verification));
            }
        } catch (\Throwable $e) {
            Log::error('Failed to send transfer notification email', [
                'verification_id' => $verification->id,
                'action'          => $action,
                'error'           => $e->getMessage(),
            ]);
        }
    }
}
