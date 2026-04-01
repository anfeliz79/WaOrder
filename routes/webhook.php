<?php

use App\Http\Controllers\Webhook\CardnetWebhookController;
use App\Http\Controllers\Webhook\WhatsAppWebhookController;
use Illuminate\Support\Facades\Route;

Route::middleware(\App\Http\Middleware\ValidateWhatsAppSignature::class)->group(function () {
    Route::get('/webhook/whatsapp', [WhatsAppWebhookController::class, 'verify']);
    Route::post('/webhook/whatsapp', [WhatsAppWebhookController::class, 'handle']);
});

// Cardnet payment webhook (no signature validation — uses session-based verification)
Route::post('/webhook/cardnet', [CardnetWebhookController::class, 'handle']);
