<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\TransferVerification;
use App\Services\Subscription\BankTransferService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TransferVerificationController extends Controller
{
    public function __construct(private BankTransferService $bankTransferService) {}

    public function index(Request $request)
    {
        $query = TransferVerification::withoutGlobalScope('tenant')
            ->with(['tenant:id,name,slug', 'bankAccount:id,bank_name,account_number', 'subscription.plan:id,name'])
            ->orderByRaw("FIELD(status, 'pending', 'expired', 'approved', 'rejected')")
            ->orderBy('deadline_at', 'asc');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $verifications = $query->paginate(25)->withQueryString();

        return Inertia::render('SuperAdmin/TransferVerifications/Index', [
            'verifications' => $verifications,
            'filters'       => $request->only('status'),
        ]);
    }

    public function show(TransferVerification $transferVerification)
    {
        $transferVerification->load([
            'tenant:id,name,slug',
            'bankAccount',
            'subscription.plan',
            'verifiedBy:id,name',
        ]);

        return Inertia::render('SuperAdmin/TransferVerifications/Show', [
            'verification' => array_merge($transferVerification->toArray(), [
                'evidence_url'   => $transferVerification->evidence_url,
                'is_image'       => $transferVerification->isImageEvidence(),
                'hours_left'     => $transferVerification->hoursUntilDeadline(),
                'is_over_deadline' => $transferVerification->isOverDeadline(),
            ]),
        ]);
    }

    public function approve(Request $request, TransferVerification $transferVerification)
    {
        if (!$transferVerification->isPending()) {
            return back()->with('error', 'Solo se pueden aprobar verificaciones en estado pendiente.');
        }

        $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->bankTransferService->approve(
            $transferVerification,
            $request->user(),
            $request->notes
        );

        return back()->with('success', "Transferencia aprobada. La suscripción de {$transferVerification->tenant->name} está activa.");
    }

    public function reject(Request $request, TransferVerification $transferVerification)
    {
        if (!$transferVerification->isPending()) {
            return back()->with('error', 'Solo se pueden rechazar verificaciones en estado pendiente.');
        }

        $request->validate([
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->bankTransferService->reject(
            $transferVerification,
            $request->user(),
            $request->notes
        );

        return back()->with('success', "Transferencia rechazada. Se notificó al restaurante {$transferVerification->tenant->name}.");
    }
}
