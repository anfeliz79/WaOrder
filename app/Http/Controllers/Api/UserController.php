<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\User;
use App\Services\Subscription\PlanEnforcement;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $users = User::with('branches:branches.id,branches.name')
            ->orderBy('name')
            ->get();

        $branches = Branch::active()
            ->orderBy('sort_order')
            ->get(['id', 'name']);

        if ($request->wantsJson()) {
            return response()->json($users);
        }

        return Inertia::render('Users/Index', [
            'users' => $users,
            'branches' => $branches,
        ]);
    }

    public function store(Request $request, PlanEnforcement $enforcement)
    {
        $tenant = app('tenant');

        $check = $enforcement->canCreateUser($tenant);
        if ($check !== true) {
            return back()->with('error', $check);
        }

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => [
                'required', 'email', 'max:255',
                Rule::unique('users')->where('tenant_id', $tenant->id),
            ],
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin,gestor',
            'branch_ids' => 'required|array|min:1',
            'branch_ids.*' => 'integer|exists:branches,id',
        ]);

        $user = User::create([
            'tenant_id' => $tenant->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
        ]);

        $user->branches()->sync($data['branch_ids']);

        return back()->with('success', 'Usuario creado');
    }

    public function update(Request $request, User $user)
    {
        $tenant = app('tenant');

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'email' => [
                'sometimes', 'email', 'max:255',
                Rule::unique('users')->where('tenant_id', $tenant->id)->ignore($user->id),
            ],
            'password' => 'nullable|string|min:6',
            'role' => 'sometimes|in:admin,gestor',
            'branch_ids' => 'sometimes|array|min:1',
            'branch_ids.*' => 'integer|exists:branches,id',
            'is_active' => 'sometimes|boolean',
        ]);

        // Don't allow deactivating yourself
        if (isset($data['is_active']) && !$data['is_active'] && $user->id === $request->user()->id) {
            return back()->withErrors(['is_active' => 'No puedes desactivar tu propia cuenta.']);
        }

        // Don't allow changing your own role
        if (isset($data['role']) && $data['role'] !== $user->role && $user->id === $request->user()->id) {
            return back()->withErrors(['role' => 'No puedes cambiar tu propio rol.']);
        }

        if (!empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $branchIds = $data['branch_ids'] ?? null;
        unset($data['branch_ids']);

        $user->update($data);

        if ($branchIds !== null) {
            $user->branches()->sync($branchIds);
        }

        return back()->with('success', 'Usuario actualizado');
    }

    public function destroy(User $user, Request $request)
    {
        if ($user->id === $request->user()->id) {
            return back()->withErrors(['user' => 'No puedes desactivar tu propia cuenta.']);
        }

        $user->update(['is_active' => false]);

        return back()->with('success', 'Usuario desactivado');
    }
}
