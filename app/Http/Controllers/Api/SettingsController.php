<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AI\AiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $tenant = app('tenant');

        if ($request->wantsJson()) {
            return response()->json($tenant);
        }

        $subscription = $tenant->subscription;
        $hasDeliveryAppAddon = $subscription
            && $subscription->addons()->where('addon_type', 'delivery_app')->where('is_active', true)->exists();

        return Inertia::render('Settings/Index', [
            'tenant' => $tenant,
            'whatsappConfig' => [
                'verify_token' => config('whatsapp.verify_token'),
                'api_version' => config('whatsapp.api_version'),
                'webhook_url' => url('/webhook/whatsapp'),
            ],
            'hasAiKey' => (bool) $tenant->ai_api_key,
            'hasWhatsAppToken' => (bool) $tenant->whatsapp_access_token,
            'hasWhatsAppAppSecret' => (bool) $tenant->whatsapp_app_secret,
            'hasDeliveryAppAddon' => $hasDeliveryAppAddon,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'timezone' => 'sometimes|string|max:50',
            'currency' => 'sometimes|string|max:3',
            // WhatsApp credentials
            'whatsapp_phone_number_id' => 'sometimes|string|max:50',
            'whatsapp_business_account_id' => 'sometimes|string|max:50',
            'whatsapp_access_token' => 'sometimes|string|max:500',
            'whatsapp_app_secret' => 'sometimes|string|max:500',
            'settings' => 'sometimes|array',
            'settings.delivery_fee' => 'nullable|numeric|min:0',
            'settings.min_order' => 'nullable|numeric|min:0',
            'settings.estimated_time' => 'nullable|integer|min:1',
            'settings.menu_source' => 'nullable|in:internal,external',
            'settings.menu_api_url' => 'nullable|url',
            'settings.menu_api_key' => 'nullable|string',
            'settings.menu_api_secret' => 'nullable|string',
            'settings.menu_api_auth_mode' => 'nullable|in:bearer,headers',
            'settings.menu_sync_interval' => 'nullable|integer|min:1',
            'settings.restaurant_phone' => 'nullable|string|max:20',
            // Payment settings
            'settings.payment' => 'sometimes|array',
            'settings.payment.methods' => 'sometimes|array|min:1',
            'settings.payment.methods.*' => 'string|max:50|regex:/^[a-z0-9_]+$/',
            'settings.payment.custom_methods' => 'sometimes|array|max:10',
            'settings.payment.custom_methods.*.name' => 'required|string|max:50',
            'settings.payment.custom_methods.*.instructions' => 'nullable|string|max:1000',
            'settings.payment.transfer_info' => 'sometimes|array',
            'settings.payment.transfer_info.bank' => 'nullable|string|max:255',
            'settings.payment.transfer_info.account_type' => 'nullable|string|in:Ahorro,Corriente',
            'settings.payment.transfer_info.account_number' => 'nullable|string|max:50',
            'settings.payment.transfer_info.holder_name' => 'nullable|string|max:255',
            'settings.payment.transfer_info.rnc' => 'nullable|string|max:20',
            'settings.payment.card_link' => 'sometimes|array',
            'settings.payment.card_link.gateway' => 'nullable|string|in:cardnet,azul',
            'settings.payment.card_link.url' => 'nullable|url|max:500',
            'settings.payment.card_link.instructions' => 'nullable|string|max:1000',
            // Business hours
            'settings.business_hours' => 'nullable|array',
            'settings.business_hours.enabled' => 'nullable|boolean',
            'settings.business_hours.open' => 'nullable|string|date_format:H:i',
            'settings.business_hours.close' => 'nullable|string|date_format:H:i',
            'settings.business_hours.days' => 'nullable|array',
            'settings.business_hours.days.*' => 'integer|between:0,6',
            'settings.business_hours.closed_message' => 'nullable|string|max:500',
            // Survey settings
            'settings.survey' => 'nullable|array',
            'settings.survey.enabled' => 'nullable|boolean',
            'settings.survey.thank_you_message' => 'nullable|string|max:500',
            'settings.survey.questions' => 'nullable|array|max:10',
            'settings.survey.questions.*.key' => 'required|string|max:50',
            'settings.survey.questions.*.label' => 'required|string|max:500',
            'settings.survey.questions.*.type' => 'required|in:rating,buttons,text',
            'settings.survey.questions.*.enabled' => 'required|boolean',
            'settings.survey.questions.*.options' => 'nullable|array|max:3',
            'settings.survey.questions.*.options.*.id' => 'required|string|max:100',
            'settings.survey.questions.*.options.*.title' => 'required|string|max:100',
            // Notification settings
            'settings.notifications' => 'nullable|array',
            'settings.notifications.sound_enabled' => 'nullable|boolean',
            'settings.notifications.polling_interval' => 'nullable|integer|min:10|max:120',
            // Tax settings
            'settings.taxes' => 'sometimes|array|max:10',
            'settings.taxes.*' => 'array',
            'settings.taxes.*.name' => 'required|string|max:50',
            'settings.taxes.*.rate' => 'required|numeric|min:0|max:100',
            'settings.taxes.*.enabled' => 'required|boolean',
            // Menu theme/branding
            'settings.menu_theme' => 'sometimes|array',
            'settings.menu_theme.primary_color' => 'nullable|string|regex:/^#[0-9a-fA-F]{6}$/',
            'settings.menu_theme.show_restaurant_name' => 'nullable|boolean',
            // EAS / Mobile app build settings
            'settings.eas' => 'sometimes|array',
            'settings.eas.token' => 'nullable|string|max:500',
            'settings.eas.mobile_path' => 'nullable|string|max:500',
            'settings.driver_mode' => 'nullable|in:whatsapp,app',
            // AI / NLP settings
            'ai_api_key' => 'sometimes|nullable|string|max:500',
            'settings.ai' => 'sometimes|array',
            'settings.ai.enabled' => 'nullable|boolean',
            'settings.ai.provider' => 'nullable|in:groq,openai',
            'settings.ai.model' => 'nullable|string|max:100',
        ]);

        $tenant = app('tenant');

        if (isset($data['settings'])) {
            $currentSettings = $tenant->settings ?? [];

            // These must be replaced entirely (not merged) to allow deletion
            $customMethods = $data['settings']['payment']['custom_methods'] ?? null;
            $taxes = $data['settings']['taxes'] ?? null;
            $surveyQuestions = $data['settings']['survey']['questions'] ?? null;

            $data['settings'] = array_replace_recursive($currentSettings, $data['settings']);

            if ($customMethods !== null) {
                $data['settings']['payment']['custom_methods'] = $customMethods;
            }
            if ($taxes !== null) {
                $data['settings']['taxes'] = array_values($taxes);
            }
            if ($surveyQuestions !== null) {
                $data['settings']['survey']['questions'] = array_values($surveyQuestions);
            }
        }

        // ai_api_key is a top-level encrypted column, not a settings key
        if (array_key_exists('ai_api_key', $data)) {
            $aiKey = $data['ai_api_key'];
            unset($data['ai_api_key']);
            // Only update if a new value was provided (empty string = clear key)
            if ($aiKey !== null) {
                $tenant->ai_api_key = $aiKey ?: null;
            }
        }

        $tenant->update($data);
        $tenant->save(); // ensures encrypted ai_api_key is persisted

        return back()->with('success', 'Configuracion actualizada');
    }

    public function uploadLogo(Request $request)
    {
        $request->validate([
            'logo' => 'required|file|mimes:jpg,jpeg,png,webp,svg|max:2048',
        ]);

        $tenant = app('tenant');

        // Delete old logo if exists
        $currentLogo = $tenant->getSetting('menu_theme.logo_path');
        if ($currentLogo && Storage::disk('public')->exists($currentLogo)) {
            Storage::disk('public')->delete($currentLogo);
        }

        $path = $request->file('logo')->store("logos/tenant-{$tenant->id}", 'public');

        $settings = $tenant->settings ?? [];
        $settings['menu_theme']['logo_path'] = $path;
        $settings['menu_theme']['logo_url'] = Storage::disk('public')->url($path);
        $tenant->update(['settings' => $settings]);

        return back()->with('success', 'Logo actualizado');
    }

    public function deleteLogo()
    {
        $tenant = app('tenant');

        $currentLogo = $tenant->getSetting('menu_theme.logo_path');
        if ($currentLogo && Storage::disk('public')->exists($currentLogo)) {
            Storage::disk('public')->delete($currentLogo);
        }

        $settings = $tenant->settings ?? [];
        unset($settings['menu_theme']['logo_path'], $settings['menu_theme']['logo_url']);
        $tenant->update(['settings' => $settings]);

        return back()->with('success', 'Logo eliminado');
    }

    public function uploadNotificationSound(Request $request)
    {
        $request->validate([
            'sound_file' => 'required|file|mimes:mp3,wav|max:2048',
        ]);

        $tenant = app('tenant');

        // Delete old sound file if exists
        $currentSound = $tenant->getSetting('notifications.custom_sound_path');
        if ($currentSound && Storage::disk('public')->exists($currentSound)) {
            Storage::disk('public')->delete($currentSound);
        }

        $path = $request->file('sound_file')->store(
            "sounds/tenant-{$tenant->id}",
            'public'
        );

        $settings = $tenant->settings ?? [];
        $settings['notifications']['custom_sound_path'] = $path;
        $tenant->update(['settings' => $settings]);

        return back()->with('success', 'Sonido de notificacion actualizado');
    }

    public function deleteNotificationSound()
    {
        $tenant = app('tenant');

        $currentSound = $tenant->getSetting('notifications.custom_sound_path');
        if ($currentSound && Storage::disk('public')->exists($currentSound)) {
            Storage::disk('public')->delete($currentSound);
        }

        $settings = $tenant->settings ?? [];
        unset($settings['notifications']['custom_sound_path']);
        $tenant->update(['settings' => $settings]);

        return back()->with('success', 'Sonido personalizado eliminado');
    }

    public function testAi()
    {
        $ai = app(AiService::class);

        if (! $ai->isAvailable()) {
            return response()->json([
                'success' => false,
                'message' => 'No hay API key configurada o el NLP está desactivado.',
            ]);
        }

        $reply = $ai->complete('Respond with only the word: OK');

        if ($reply && str_contains(strtoupper($reply), 'OK')) {
            return response()->json(['success' => true, 'message' => "Conexión exitosa. Respuesta: \"{$reply}\"."]);
        }

        if ($reply) {
            return response()->json(['success' => true, 'message' => "Conexión exitosa. Respuesta recibida: \"{$reply}\"."]);
        }

        return response()->json(['success' => false, 'message' => 'La API no respondió. Verifica la clave.']);
    }

    public function testWhatsApp(Request $request)
    {
        $tenant = app('tenant');

        if (!$tenant->whatsapp_phone_number_id || !$tenant->whatsapp_access_token) {
            return response()->json([
                'success' => false,
                'message' => 'Faltan credenciales de WhatsApp. Configura el Phone Number ID y el Access Token.',
            ]);
        }

        $apiVersion = config('whatsapp.api_version', 'v21.0');
        $baseUrl = config('whatsapp.api_base_url', 'https://graph.facebook.com');

        try {
            $response = Http::withToken($tenant->whatsapp_access_token)
                ->timeout(10)
                ->get("{$baseUrl}/{$apiVersion}/{$tenant->whatsapp_phone_number_id}");

            if ($response->successful()) {
                $data = $response->json();
                return response()->json([
                    'success' => true,
                    'message' => 'Conexion exitosa',
                    'details' => [
                        'phone' => $data['display_phone_number'] ?? null,
                        'name' => $data['verified_name'] ?? null,
                        'quality' => $data['quality_rating'] ?? null,
                    ],
                ]);
            }

            $error = $response->json('error.message', 'Error desconocido');
            return response()->json([
                'success' => false,
                'message' => $error,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'No se pudo conectar: ' . $e->getMessage(),
            ]);
        }
    }
}
