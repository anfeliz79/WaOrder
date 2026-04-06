<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\TransferVerification;
use App\Services\Subscription\BankTransferService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class RegistrationBankTransferController extends Controller
{
    public function __construct(private BankTransferService $bankTransferService) {}

    /**
     * Show the pending-verification waiting page.
     */
    public function pending()
    {
        $tenant       = app('tenant');
        $subscription = $tenant->subscription;

        if ($subscription?->isActive()) {
            return redirect('/setup');
        }

        // Get the latest verification for this tenant's subscription
        $verification = $subscription
            ? TransferVerification::withoutGlobalScope('tenant')
                ->where('subscription_id', $subscription->id)
                ->with('bankAccount')
                ->latest()
                ->first()
            : null;

        $user = auth()->user();

        return Inertia::render('Auth/RegisterBankTransferPending', [
            'verification' => $verification ? [
                'id'               => $verification->id,
                'status'           => $verification->status,
                'amount'           => $verification->amount,
                'reference_number' => $verification->reference_number,
                'deadline_at'      => $verification->deadline_at?->toIso8601String(),
                'created_at'       => $verification->created_at?->toIso8601String(),
                'verified_at'      => $verification->verified_at?->toIso8601String(),
                'admin_notes'      => $verification->admin_notes,
                'bank_account'     => $verification->bankAccount ? [
                    'bank_name'           => $verification->bankAccount->bank_name,
                    'account_holder_name' => $verification->bankAccount->account_holder_name,
                    'account_number'      => $verification->bankAccount->account_number,
                ] : null,
            ] : null,
            'tenant_name'      => $tenant->name,
            'user_name'        => $user?->name,
            'user_email'       => $user?->email,
            'whatsapp_contact' => config('app.whatsapp_contact', ''),
        ]);
    }

    /**
     * Store the uploaded evidence and create a pending transfer verification.
     */
    public function submit(Request $request)
    {
        $request->validate([
            'bank_account_id'  => ['required', 'integer', 'exists:bank_accounts,id'],
            'reference_number' => ['nullable', 'string', 'max:100'],
            'evidence'         => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], [
            'bank_account_id.required' => 'Debes seleccionar una cuenta bancaria.',
            'bank_account_id.exists'   => 'La cuenta bancaria seleccionada no es válida.',
            'evidence.required'        => 'Debes adjuntar el comprobante de pago.',
            'evidence.mimes'           => 'El comprobante debe ser una imagen (JPG, PNG, WEBP) o PDF.',
            'evidence.max'             => 'El comprobante no puede superar los 5 MB.',
        ]);

        $tenant       = app('tenant');
        $subscription = $tenant->subscription;

        if (!$subscription || $subscription->status !== 'pending_payment') {
            return back()->withErrors(['general' => 'No hay suscripción pendiente de pago.']);
        }

        // Only one pending verification at a time
        $existingPending = TransferVerification::withoutGlobalScope('tenant')
            ->where('subscription_id', $subscription->id)
            ->where('status', 'pending')
            ->exists();

        if ($existingPending) {
            return redirect('/register/bank-transfer/pending');
        }

        $bankAccount = BankAccount::where('id', $request->bank_account_id)
            ->where('is_active', true)
            ->firstOrFail();

        $this->bankTransferService->submitEvidence(
            tenant: $tenant,
            subscription: $subscription,
            bankAccount: $bankAccount,
            amount: (float) $subscription->price,
            referenceNumber: $request->reference_number,
            file: $request->file('evidence'),
        );

        return redirect('/register/bank-transfer/pending');
    }
}
