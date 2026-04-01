<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index()
    {
        $aiProvider = config('ai.default_provider', 'groq');
        $aiKey = config("ai.providers.{$aiProvider}.api_key");

        return Inertia::render('SuperAdmin/Settings', [
            'settings' => [
                'cardnet_environment' => config('cardnet.environment'),
                'cardnet_public_key' => config('cardnet.platform.public_key') ? '••••' . substr(config('cardnet.platform.public_key'), -8) : null,
                'cardnet_private_key' => config('cardnet.platform.private_key') ? '••••' . substr(config('cardnet.platform.private_key'), -8) : null,
                'cardnet_has_keys' => !empty(config('cardnet.platform.public_key')) && !empty(config('cardnet.platform.private_key')),
                'whatsapp_contact' => config('app.whatsapp_contact', ''),
                'ai_provider' => $aiProvider,
                'ai_model' => config("ai.providers.{$aiProvider}.model", ''),
                'ai_has_key' => !empty($aiKey),
                'ai_api_key' => $aiKey ? '••••' . substr($aiKey, -8) : null,
            ],
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'cardnet_environment' => ['required', 'in:testing,production'],
            'cardnet_public_key' => ['nullable', 'string'],
            'cardnet_private_key' => ['nullable', 'string'],
            'whatsapp_contact' => ['nullable', 'string', 'max:20'],
            'ai_provider' => ['required', 'in:groq,openai'],
            'ai_api_key' => ['nullable', 'string'],
        ]);

        $envPath = base_path('.env');
        $env = file_get_contents($envPath);

        $updates = [
            'CARDNET_ENVIRONMENT' => $validated['cardnet_environment'],
            'AI_PROVIDER' => $validated['ai_provider'],
        ];

        if (!empty($validated['whatsapp_contact'])) {
            $updates['WHATSAPP_CONTACT'] = $validated['whatsapp_contact'];
        }

        // Only update keys if new values were provided (not masked)
        if (!empty($validated['cardnet_public_key']) && !str_starts_with($validated['cardnet_public_key'], '••••')) {
            $updates['CARDNET_PUBLIC_KEY'] = $validated['cardnet_public_key'];
        }
        if (!empty($validated['cardnet_private_key']) && !str_starts_with($validated['cardnet_private_key'], '••••')) {
            $updates['CARDNET_PRIVATE_KEY'] = $validated['cardnet_private_key'];
        }

        // AI API key
        if (!empty($validated['ai_api_key']) && !str_starts_with($validated['ai_api_key'], '••••')) {
            $envKey = $validated['ai_provider'] === 'openai' ? 'OPENAI_API_KEY' : 'GROQ_API_KEY';
            $updates[$envKey] = $validated['ai_api_key'];
        }

        foreach ($updates as $key => $value) {
            if (preg_match("/^{$key}=.*/m", $env)) {
                $env = preg_replace("/^{$key}=.*/m", "{$key}={$value}", $env);
            } else {
                $env .= "\n{$key}={$value}";
            }
        }

        file_put_contents($envPath, $env);

        return back()->with('success', 'Configuracion actualizada. Los cambios se aplicaran en el proximo request.');
    }
}
