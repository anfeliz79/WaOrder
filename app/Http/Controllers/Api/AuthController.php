<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Intentar login normal (con tenant scope)
        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            // Si falla, intentar como SuperAdmin (sin tenant scope, tenant_id = NULL)
            $superAdmin = User::withoutGlobalScope('tenant')
                ->where('email', $credentials['email'])
                ->where('role', 'superadmin')
                ->first();

            if ($superAdmin && Hash::check($credentials['password'], $superAdmin->password)) {
                Auth::login($superAdmin, $request->boolean('remember'));
            } else {
                return back()->withErrors([
                    'email' => 'Las credenciales no coinciden.',
                ]);
            }
        }

        $request->session()->regenerate();

        $user = Auth::user();
        $user->update(['last_login_at' => now()]);

        // SuperAdmin redirects to superadmin dashboard
        if ($user->isSuperAdmin()) {
            return redirect()->intended('/superadmin');
        }

        // Determine branches the user can access
        $branches = $this->getAccessibleBranches($user);

        // Order taker: redirect to console
        if ($user->isOrderTaker()) {
            if ($branches->count() === 1) {
                session(['current_branch_id' => $branches->first()->id]);
                return redirect()->intended('/console');
            }
            if ($branches->count() > 1) {
                return redirect('/select-branch?next=console');
            }
            return redirect()->intended('/console');
        }

        if ($branches->count() === 1) {
            session(['current_branch_id' => $branches->first()->id]);
            return redirect()->intended('/dashboard');
        }

        if ($branches->count() > 1) {
            return redirect('/select-branch');
        }

        // No branches yet, proceed to dashboard (admin will need to create branches)
        return redirect()->intended('/dashboard');
    }

    public function showSelectBranch(Request $request)
    {
        $user = $request->user();
        $branches = $this->getAccessibleBranches($user);
        $next = $request->query('next', $user->isOrderTaker() ? 'console' : 'dashboard');

        if ($branches->count() <= 1) {
            if ($branches->count() === 1) {
                session(['current_branch_id' => $branches->first()->id]);
            }
            return redirect('/' . $next);
        }

        return Inertia::render('Auth/SelectBranch', [
            'branches' => $branches->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'address' => $b->address,
                'phone' => $b->phone,
            ]),
            'next' => $next,
        ]);
    }

    public function selectBranch(Request $request)
    {
        $request->validate([
            'branch_id' => 'required|integer',
            'next' => 'nullable|string|in:dashboard,console',
        ]);

        $user = $request->user();

        if (!$user->canAccessBranch($request->branch_id)) {
            return back()->withErrors(['branch_id' => 'No tienes acceso a esta sucursal.']);
        }

        session(['current_branch_id' => $request->branch_id]);

        $next = $request->input('next', $user->isOrderTaker() ? 'console' : 'dashboard');

        return redirect('/' . $next);
    }

    public function switchBranch(Request $request)
    {
        $request->validate([
            'branch_id' => 'required|integer',
        ]);

        $user = $request->user();

        if (!$user->canAccessBranch($request->branch_id)) {
            return back()->withErrors(['branch_id' => 'No tienes acceso a esta sucursal.']);
        }

        session(['current_branch_id' => $request->branch_id]);

        return back();
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    public function user(Request $request)
    {
        return response()->json([
            'user' => $request->user()->load('tenant'),
        ]);
    }

    private function getAccessibleBranches($user)
    {
        if ($user->isAdmin()) {
            return Branch::where('tenant_id', $user->tenant_id)
                ->active()
                ->orderBy('sort_order')
                ->get();
        }

        return $user->branches()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->get();
    }
}
