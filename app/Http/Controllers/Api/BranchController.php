<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Services\Subscription\PlanEnforcement;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BranchController extends Controller
{
    public function index(Request $request)
    {
        $branches = Branch::orderBy('sort_order')
            ->orderBy('name')
            ->withCount('orders', 'drivers')
            ->get();

        if ($request->wantsJson()) {
            return response()->json($branches);
        }

        return Inertia::render('Branches/Index', [
            'branches' => $branches,
        ]);
    }

    public function store(Request $request, PlanEnforcement $enforcement)
    {
        $check = $enforcement->canCreateBranch(app('tenant'));
        if ($check !== true) {
            return back()->with('error', $check);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'phone' => 'nullable|string|max:20',
            'max_delivery_distance_km' => 'required|numeric|min:0.5|max:100',
            'is_active' => 'boolean',
        ]);

        Branch::create($data);

        return back()->with('success', 'Sucursal creada');
    }

    public function update(Request $request, Branch $branch)
    {
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'address' => 'sometimes|string|max:500',
            'latitude' => 'nullable|numeric|between:-90,90',
            'longitude' => 'nullable|numeric|between:-180,180',
            'phone' => 'nullable|string|max:20',
            'max_delivery_distance_km' => 'sometimes|numeric|min:0.5|max:100',
            'is_active' => 'boolean',
            'settings' => 'nullable|array',
            'settings.delivery_fee' => 'nullable|numeric|min:0',
        ]);

        $branch->update($data);

        return back()->with('success', 'Sucursal actualizada');
    }

    public function destroy(Branch $branch)
    {
        $branch->update(['is_active' => false]);

        return back()->with('success', 'Sucursal desactivada');
    }
}
