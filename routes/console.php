<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Subscription billing jobs
Schedule::job(new \App\Jobs\ProcessSubscriptionBilling)->daily()->at('06:00');
Schedule::job(new \App\Jobs\ConvertExpiredTrials)->daily()->at('06:15');
Schedule::job(new \App\Jobs\SuspendExpiredSubscriptions)->daily()->at('06:30');

// Check pending Cardnet payment sessions
Schedule::job(new \App\Jobs\CheckCardnetPaymentStatus)->everyFiveMinutes();

// Expire bank transfer verifications past their 12-hour deadline
Schedule::job(new \App\Jobs\ProcessExpiredTransferVerifications)->hourly();
