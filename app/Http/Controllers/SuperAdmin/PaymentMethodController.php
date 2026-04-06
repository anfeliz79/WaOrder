<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PaymentMethodController extends Controller
{
    public function index()
    {
        return Inertia::render('SuperAdmin/PaymentMethods/Index', [
            'methods' => PaymentMethod::orderBy('sort_order')->get(),
        ]);
    }

    public function toggleActive(PaymentMethod $method)
    {
        // Validation: don't allow activating PayPal without config
        if (!$method->is_active && $method->slug === 'paypal') {
            $config = $method->config ?? [];
            if (empty($config['client_id']) || empty($config['client_secret'])) {
                return back()->with('error', 'Configura las credenciales de PayPal antes de activar este metodo.');
            }
        }

        // Validation: don't allow activating Cardnet without env config
        if (!$method->is_active && $method->slug === 'cardnet') {
            if (empty(config('services.cardnet.public_key'))) {
                return back()->with('error', 'Configura las credenciales de Cardnet en el servidor antes de activar este metodo.');
            }
        }

        $method->update(['is_active' => !$method->is_active]);
        $action = $method->is_active ? 'activado' : 'desactivado';

        return back()->with('success', "{$method->name} {$action}");
    }

    public function updateConfig(Request $request, PaymentMethod $method)
    {
        $rules = [];

        if ($method->slug === 'paypal') {
            $rules = [
                'client_id'     => 'required|string',
                'client_secret' => 'required|string',
                'mode'          => 'required|in:sandbox,live',
                'webhook_id'    => 'nullable|string',
            ];
        }

        $data = $request->validate($rules);

        $method->update(['config' => $data]);

        return back()->with('success', 'Configuracion actualizada');
    }
}
