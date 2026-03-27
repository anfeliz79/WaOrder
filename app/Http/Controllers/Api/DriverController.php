<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Driver;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DriverController extends Controller
{
    public function index(Request $request)
    {
        $branch = app()->bound('branch') ? app('branch') : null;

        $query = Driver::active()->withCount('activeOrders');

        if ($branch) {
            $query->where('branch_id', $branch->id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        $drivers = $query->orderBy('name')->get();

        if ($request->wantsJson()) {
            return response()->json($drivers);
        }

        return Inertia::render('Drivers/Index', [
            'drivers' => $drivers,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'vehicle_type' => 'nullable|string|in:moto,carro,bicicleta',
            'vehicle_plate' => 'nullable|string|max:20',
        ]);

        $branch = app()->bound('branch') ? app('branch') : null;
        if ($branch) {
            $data['branch_id'] = $branch->id;
        }

        Driver::create($data);

        return back()->with('success', 'Mensajero registrado');
    }

    public function update(Request $request, Driver $driver)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'vehicle_type' => 'nullable|string|in:moto,carro,bicicleta',
            'vehicle_plate' => 'nullable|string|max:20',
            'is_active' => 'nullable|boolean',
            'is_available' => 'nullable|boolean',
        ]);

        $driver->update($data);

        return back()->with('success', 'Mensajero actualizado');
    }

    public function destroy(Driver $driver)
    {
        $driver->update(['is_active' => false]);

        return back()->with('success', 'Mensajero desactivado');
    }

    public function toggleAvailability(Driver $driver)
    {
        $driver->update(['is_available' => !$driver->is_available]);

        return back()->with('success', 'Disponibilidad actualizada');
    }

    public function generateQrToken(Driver $driver)
    {
        $token = $driver->generateLinkingToken();
        $tenant = app('tenant');

        $qrPayload = json_encode([
            'tenant_slug' => $tenant->slug,
            'driver_id' => $driver->id,
            'token' => $token,
            'api_url' => config('app.url'),
        ]);

        return response()->json([
            'qr_data' => $qrPayload,
            'expires_at' => $driver->fresh()->linking_token_expires_at->toIso8601String(),
        ]);
    }
}
