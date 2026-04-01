<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Inertia\Inertia;

class RegisterController extends Controller
{
    public function showForm(Request $request)
    {
        $plans = Plan::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return Inertia::render('Auth/Register', [
            'plans' => $plans,
            'selectedPlan' => $request->query('plan'),
        ]);
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'restaurant_name' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'plan_slug' => ['required', 'string', 'exists:plans,slug'],
        ], [
            'restaurant_name.required' => 'El nombre del restaurante es requerido.',
            'name.required' => 'Tu nombre es requerido.',
            'email.required' => 'El email es requerido.',
            'email.unique' => 'Este email ya esta registrado.',
            'password.min' => 'La contrasena debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contrasenas no coinciden.',
        ]);

        $plan = Plan::where('slug', $validated['plan_slug'])->firstOrFail();

        return DB::transaction(function () use ($validated, $plan, $request) {
            // Generate unique slug
            $slug = Str::slug($validated['restaurant_name']);
            $baseSlug = $slug;
            $counter = 1;
            while (Tenant::where('slug', $slug)->exists()) {
                $slug = $baseSlug . '-' . $counter++;
            }

            // Create tenant
            $tenant = Tenant::create([
                'name' => $validated['restaurant_name'],
                'slug' => $slug,
                'timezone' => 'America/Santo_Domingo',
                'currency' => 'DOP',
                'locale' => 'es',
                'plan_id' => $plan->id,
                'subscription_plan' => $plan->slug, // backward compat
                'is_active' => true,
                'settings' => [
                    'setup_completed' => false,
                    'delivery_enabled' => true,
                    'pickup_enabled' => true,
                ],
            ]);

            // Create admin user
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => Hash::make($validated['password']),
                'role' => 'admin',
                'tenant_id' => $tenant->id,
            ]);

            // Create subscription
            $isFreePlan = $plan->isFree();

            Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => $isFreePlan ? 'active' : 'pending_payment',
                'billing_period' => 'monthly',
                'price' => $plan->price_monthly,
                'current_period_start' => now(),
                'current_period_end' => $isFreePlan ? now()->addYear() : now()->addMonth(),
            ]);

            // Login
            Auth::login($user);
            $request->session()->regenerate();

            // Free plan → setup wizard; paid plan → payment step
            return $isFreePlan ? redirect('/setup') : redirect('/register/payment');
        });
    }
}
