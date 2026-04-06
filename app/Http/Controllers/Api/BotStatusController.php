<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Cache;

class BotStatusController extends Controller
{
    public function togglePause()
    {
        $tenant = app('tenant');

        if (!$tenant) {
            return back()->with('error', 'No se pudo identificar el restaurante.');
        }

        $settings = $tenant->settings ?? [];
        $settings['bot_paused'] = !($settings['bot_paused'] ?? false);
        $tenant->update(['settings' => $settings]);

        Cache::forget("bot_status_{$tenant->id}");

        return back()->with('success', $settings['bot_paused'] ? 'Bot pausado' : 'Bot reactivado');
    }
}
