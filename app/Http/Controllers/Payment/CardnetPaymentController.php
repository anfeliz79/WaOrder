<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Models\CardnetPaymentSession;
use App\Services\Notification\NotificationService;
use App\Services\Payment\CardnetService;
use Illuminate\Http\Request;

class CardnetPaymentController extends Controller
{
    public function __construct(
        private CardnetService $cardnetService,
    ) {}

    /**
     * Show payment page or redirect to Cardnet.
     * GET /pay/{uuid}
     */
    public function show(string $uuid)
    {
        $session = CardnetPaymentSession::where('uuid', $uuid)->firstOrFail();

        if ($session->status !== 'pending') {
            return view('payment.status', [
                'session' => $session,
                'order' => $session->order,
            ]);
        }

        if ($session->isExpired()) {
            $session->update(['status' => 'expired']);
            return view('payment.status', [
                'session' => $session,
                'order' => $session->order,
            ]);
        }

        // If session has a Cardnet authorize URL, redirect
        $authorizeUrl = $session->cardnet_response['authorize_url']
            ?? $session->cardnet_response['AuthorizeURL']
            ?? null;

        if ($authorizeUrl) {
            return redirect()->away($authorizeUrl);
        }

        // Fallback: show payment info page
        return view('payment.checkout', [
            'session' => $session,
            'order' => $session->order,
            'tenant' => $session->tenant,
        ]);
    }

    /**
     * Return URL callback from Cardnet after successful payment.
     * GET /pay/{uuid}/success
     */
    public function success(string $uuid, Request $request)
    {
        $session = CardnetPaymentSession::where('uuid', $uuid)->firstOrFail();

        $callbackData = $request->all();
        $this->cardnetService->handlePaymentSuccess($session, $callbackData);

        // Send WhatsApp confirmation
        try {
            $order = $session->order;
            $tenant = $session->tenant;
            if ($order && $tenant) {
                app(NotificationService::class)->sendPaymentConfirmation($order, $tenant);
            }
        } catch (\Exception $e) {
            // Don't fail the payment callback over notification errors
        }

        return view('payment.status', [
            'session' => $session->fresh(),
            'order' => $session->order,
        ]);
    }

    /**
     * Cancel URL callback from Cardnet.
     * GET /pay/{uuid}/cancel
     */
    public function cancel(string $uuid)
    {
        $session = CardnetPaymentSession::where('uuid', $uuid)->firstOrFail();

        $this->cardnetService->handlePaymentCancel($session);

        return view('payment.status', [
            'session' => $session->fresh(),
            'order' => $session->order,
        ]);
    }
}
