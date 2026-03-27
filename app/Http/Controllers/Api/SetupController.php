<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Models\Tenant;
use App\Models\User;
use App\Services\WhatsApp\WhatsAppClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Inertia\Inertia;

class SetupController extends Controller
{
    public function index()
    {
        $tenant = app('tenant');
        $user = auth()->user();

        return Inertia::render('Setup/Wizard', [
            'tenant' => [
                'name' => $tenant->name,
                'timezone' => $tenant->timezone,
                'currency' => $tenant->currency,
                'settings' => $tenant->settings ?? [],
                'whatsapp_phone_number_id' => $tenant->whatsapp_phone_number_id === 'DEMO_PHONE_ID' ? '' : $tenant->whatsapp_phone_number_id,
                'whatsapp_business_account_id' => $tenant->whatsapp_business_account_id === 'DEMO_BUSINESS_ID' ? '' : $tenant->whatsapp_business_account_id,
            ],
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
            ],
            'verifyToken' => config('whatsapp.verify_token'),
            'hasCategories' => MenuCategory::count() > 0,
        ]);
    }

    public function saveRestaurant(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'timezone' => 'required|string|max:50',
            'currency' => 'required|string|max:3',
            'delivery_fee' => 'required|numeric|min:0',
            'min_order' => 'nullable|numeric|min:0',
            'estimated_time' => 'required|integer|min:1|max:180',
        ]);

        $tenant = app('tenant');
        $settings = $tenant->settings ?? [];
        $settings['delivery_fee'] = $data['delivery_fee'];
        $settings['min_order'] = $data['min_order'] ?? 0;
        $settings['estimated_time'] = $data['estimated_time'];

        $tenant->update([
            'name' => $data['name'],
            'slug' => Str::slug($data['name']),
            'timezone' => $data['timezone'],
            'currency' => $data['currency'],
            'settings' => $settings,
        ]);

        return back()->with('success', 'Datos del restaurante guardados');
    }

    public function saveWhatsApp(Request $request)
    {
        $data = $request->validate([
            'whatsapp_phone_number_id' => 'required|string|max:50',
            'whatsapp_business_account_id' => 'required|string|max:50',
            'whatsapp_access_token' => 'required|string',
        ]);

        $tenant = app('tenant');
        $tenant->update($data);

        return back()->with('success', 'Credenciales de WhatsApp guardadas');
    }

    public function testWhatsApp(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
        ]);

        $tenant = app('tenant');

        if ($tenant->whatsapp_phone_number_id === 'DEMO_PHONE_ID') {
            return response()->json(['success' => false, 'error' => 'Configura las credenciales de WhatsApp primero.'], 422);
        }

        try {
            $client = app(WhatsAppClient::class);
            $result = $client->sendTextMessage(
                $tenant,
                $request->phone,
                'Hola! Este es un mensaje de prueba de WaOrder. Si ves este mensaje, la conexion con WhatsApp funciona correctamente.'
            );

            return response()->json(['success' => (bool) $result]);
        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 422);
        }
    }

    public function saveMenu(Request $request)
    {
        $data = $request->validate([
            'menu_source' => 'required|in:internal,external',
            'categories' => 'nullable|array',
            'categories.*.name' => 'required_with:categories|string|max:255',
            'categories.*.items' => 'required_with:categories|array|min:1',
            'categories.*.items.*.name' => 'required|string|max:255',
            'categories.*.items.*.price' => 'required|numeric|min:0',
            'categories.*.items.*.description' => 'nullable|string',
            'menu_api_url' => 'required_if:menu_source,external|nullable|url',
            'menu_api_key' => 'nullable|string',
        ]);

        $tenant = app('tenant');

        if ($data['menu_source'] === 'internal' && !empty($data['categories'])) {
            // Delete demo menu
            MenuItem::withoutGlobalScopes()->where('tenant_id', $tenant->id)->delete();
            MenuCategory::withoutGlobalScopes()->where('tenant_id', $tenant->id)->delete();

            foreach ($data['categories'] as $i => $catData) {
                $category = MenuCategory::create([
                    'tenant_id' => $tenant->id,
                    'name' => $catData['name'],
                    'sort_order' => $i + 1,
                ]);

                foreach ($catData['items'] as $j => $itemData) {
                    MenuItem::create([
                        'tenant_id' => $tenant->id,
                        'category_id' => $category->id,
                        'name' => $itemData['name'],
                        'description' => $itemData['description'] ?? null,
                        'price' => $itemData['price'],
                        'sort_order' => $j + 1,
                    ]);
                }
            }
        } elseif ($data['menu_source'] === 'external') {
            $settings = $tenant->settings ?? [];
            $settings['menu_source'] = 'external';
            $settings['menu_api_url'] = $data['menu_api_url'];
            $settings['menu_api_key'] = $data['menu_api_key'];
            $tenant->update(['settings' => $settings]);
        }

        return back()->with('success', 'Menu guardado');
    }

    public function testMenuApi(Request $request)
    {
        $request->validate([
            'url' => 'required|url',
            'api_key' => 'nullable|string',
        ]);

        try {
            $http = Http::timeout(10);
            if ($request->api_key) {
                $http = $http->withHeaders(['Authorization' => $request->api_key]);
            }
            $response = $http->get($request->url);

            if ($response->successful()) {
                return response()->json([
                    'success' => true,
                    'preview' => $response->json(),
                ]);
            }

            return response()->json([
                'success' => false,
                'error' => "API respondio con status {$response->status()}",
            ], 422);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 422);
        }
    }

    public function saveAdmin(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $user = auth()->user();
        $user->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ]);

        return back()->with('success', 'Cuenta actualizada');
    }

    public function complete()
    {
        $tenant = app('tenant');
        $settings = $tenant->settings ?? [];
        $settings['setup_completed'] = true;
        $tenant->update(['settings' => $settings]);

        return redirect('/dashboard');
    }
}
