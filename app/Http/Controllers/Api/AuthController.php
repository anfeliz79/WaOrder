<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (!Auth::attempt($credentials, $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'Las credenciales no coinciden.',
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();
        $user->update(['last_login_at' => now()]);

        // Determine branches the user can access
        $branches = $this->getAccessibleBranches($user);

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

        if ($branches->count() <= 1) {
            if ($branches->count() === 1) {
                session(['current_branch_id' => $branches->first()->id]);
            }
            return redirect('/dashboard');
        }

        return Inertia::render('Auth/SelectBranch', [
            'branches' => $branches->map(fn ($b) => [
                'id' => $b->id,
                'name' => $b->name,
                'address' => $b->address,
                'phone' => $b->phone,
            ]),
        ]);
    }

    public function selectBranch(Request $request)
    {
        $request->validate([
            'branch_id' => 'required|integer',
        ]);

        $user = $request->user();

        if (!$user->canAccessBranch($request->branch_id)) {
            return back()->withErrors(['branch_id' => 'No tienes acceso a esta sucursal.']);
        }

        session(['current_branch_id' => $request->branch_id]);

        return redirect('/dashboard');
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
