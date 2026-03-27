<?php

namespace App\Jobs;

use App\Services\Session\SessionManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class CleanExpiredSessions implements ShouldQueue
{
    use Queueable;

    public function handle(SessionManager $sessionManager): void
    {
        $count = $sessionManager->cleanExpired();

        if ($count > 0) {
            Log::info("Cleaned {$count} expired sessions");
        }
    }
}
