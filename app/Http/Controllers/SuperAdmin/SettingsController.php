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

        $mailPassword = config('mail.mailers.smtp.password');

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
                // SMTP
                'mail_mailer' => config('mail.default', 'log'),
                'mail_host' => config('mail.mailers.smtp.host', ''),
                'mail_port' => config('mail.mailers.smtp.port', ''),
                'mail_username' => config('mail.mailers.smtp.username', ''),
                'mail_has_password' => !empty($mailPassword) && $mailPassword !== 'null',
                'mail_password' => (!empty($mailPassword) && $mailPassword !== 'null') ? '••••' . substr($mailPassword, -4) : null,
                'mail_encryption' => config('mail.mailers.smtp.encryption', 'tls'),
                'mail_from_address' => config('mail.from.address', ''),
                'mail_from_name' => config('mail.from.name', ''),
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
            // SMTP
            'mail_mailer' => ['required', 'in:smtp,log'],
            'mail_host' => ['nullable', 'string', 'max:255'],
            'mail_port' => ['nullable', 'string', 'max:10'],
            'mail_username' => ['nullable', 'string', 'max:255'],
            'mail_password' => ['nullable', 'string'],
            'mail_encryption' => ['nullable', 'in:tls,ssl,null'],
            'mail_from_address' => ['nullable', 'email', 'max:255'],
            'mail_from_name' => ['nullable', 'string', 'max:100'],
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

        // SMTP
        $updates['MAIL_MAILER'] = $validated['mail_mailer'];
        if (!empty($validated['mail_host'])) {
            $updates['MAIL_HOST'] = $validated['mail_host'];
        }
        if (!empty($validated['mail_port'])) {
            $updates['MAIL_PORT'] = $validated['mail_port'];
        }
        if (!empty($validated['mail_username'])) {
            $updates['MAIL_USERNAME'] = $validated['mail_username'];
        }
        if (!empty($validated['mail_password']) && !str_starts_with($validated['mail_password'], '••••')) {
            $updates['MAIL_PASSWORD'] = $validated['mail_password'];
        }
        $updates['MAIL_ENCRYPTION'] = $validated['mail_encryption'] ?? 'tls';
        if (!empty($validated['mail_from_address'])) {
            $updates['MAIL_FROM_ADDRESS'] = '"' . $validated['mail_from_address'] . '"';
        }
        if (!empty($validated['mail_from_name'])) {
            $updates['MAIL_FROM_NAME'] = '"' . $validated['mail_from_name'] . '"';
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
