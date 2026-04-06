<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantNotice;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TenantNoticeController extends Controller
{
    public function index(Request $request)
    {
        $query = TenantNotice::with(['tenant', 'createdBy'])->latest();

        if ($type = $request->input('type')) {
            $query->where('type', $type);
        }

        if ($request->input('active_only')) {
            $query->active();
        }

        if ($tenantId = $request->input('tenant_id')) {
            if ($tenantId === 'global') {
                $query->whereNull('tenant_id');
            } else {
                $query->where('tenant_id', $tenantId);
            }
        }

        $notices = $query->paginate(15)->withQueryString();

        $tenants = Tenant::orderBy('name')
            ->get(['id', 'name'])
            ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name]);

        return Inertia::render('SuperAdmin/Notices/Index', [
            'notices' => $notices,
            'tenants' => $tenants,
            'filters' => $request->only(['type', 'active_only', 'tenant_id']),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'tenant_id' => 'nullable|exists:tenants,id',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
            'type' => 'required|in:info,warning,danger,success',
            'is_active' => 'boolean',
            'dismissible' => 'boolean',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        $validated['created_by'] = auth()->id();

        TenantNotice::create($validated);

        return back()->with('success', 'Aviso creado correctamente.');
    }

    public function update(TenantNotice $notice, Request $request)
    {
        $validated = $request->validate([
            'tenant_id' => 'nullable|exists:tenants,id',
            'title' => 'required|string|max:255',
            'message' => 'required|string|max:2000',
            'type' => 'required|in:info,warning,danger,success',
            'is_active' => 'boolean',
            'dismissible' => 'boolean',
            'starts_at' => 'nullable|date',
            'expires_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        $notice->update($validated);

        return back()->with('success', 'Aviso actualizado.');
    }

    public function toggleActive(TenantNotice $notice)
    {
        $notice->update(['is_active' => !$notice->is_active]);

        $state = $notice->is_active ? 'activado' : 'desactivado';

        return back()->with('success', "Aviso {$state}.");
    }

    public function destroy(TenantNotice $notice)
    {
        $notice->delete();

        return back()->with('success', 'Aviso eliminado.');
    }
}
