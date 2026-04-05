<?php

namespace App\Jobs;

use App\Models\TransferVerification;
use App\Services\Subscription\BankTransferService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessExpiredTransferVerifications implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(BankTransferService $service): void
    {
        $expired = TransferVerification::withoutGlobalScope('tenant')
            ->where('status', 'pending')
            ->where('deadline_at', '<', now())
            ->get();

        if ($expired->isEmpty()) {
            return;
        }

        foreach ($expired as $verification) {
            $service->expire($verification);
        }

        Log::info('Expired transfer verifications processed', ['count' => $expired->count()]);
    }
}
