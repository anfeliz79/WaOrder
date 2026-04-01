<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ImpersonationController extends Controller
{
    public function impersonate(Request $request, int $tenantId)
    {
        // Ensure current user is superadmin
        $superAdmin = $request->user();
        if (!$superAdmin || !$superAdmin->isSuperAdmin()) {
            abort(403, 'Solo el SuperAdmin puede usar esta función.');
        }

        // Find the first admin user of the tenant (bypass BelongsToTenant scope)
        $adminUser = User::withoutGlobalScope('tenant')
            ->where('tenant_id', $tenantId)
            ->where('role', 'admin')
            ->first();

        if (!$adminUser) {
            return back()->with('error', 'Este restaurante no tiene usuario admin.');
        }

        // Store superadmin ID in session before switching
        session(['impersonating_id' => $superAdmin->id]);

        Auth::login($adminUser);
        $request->session()->regenerate();

        return redirect('/dashboard');
    }

    public function leave(Request $request)
    {
        $superAdminId = session('impersonating_id');

        if (!$superAdminId) {
            return redirect('/superadmin/tenants');
        }

        // Restore original superadmin session (bypass BelongsToTenant scope)
        $superAdmin = User::withoutGlobalScope('tenant')->find($superAdminId);

        if (!$superAdmin || !$superAdmin->isSuperAdmin()) {
            // Safety fallback: clear impersonation and redirect to login
            session()->forget('impersonating_id');
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/login');
        }

        session()->forget('impersonating_id');

        Auth::login($superAdmin);
        $request->session()->regenerate();

        return redirect('/superadmin/tenants');
    }
}
