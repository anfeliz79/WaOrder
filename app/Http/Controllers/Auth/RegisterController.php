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
            'billing_period' => ['required', 'in:monthly,annual'],
        ], [
            'restaurant_name.required' => 'El nombre del restaurante es requerido.',
            'name.required' => 'Tu nombre es requerido.',
            'email.required' => 'El email es requerido.',
            'email.unique' => 'Este email ya esta registrado.',
            'password.min' => 'La contrasena debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contrasenas no coinciden.',
        ]);

        $plan = Plan::where('slug', $validated['plan_slug'])->firstOrFail();
        $billingPeriod = $validated['billing_period'];
        $price = $plan->getPriceForPeriod($billingPeriod);

        return DB::transaction(function () use ($validated, $plan, $billingPeriod, $price, $request) {
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
                'currency' => $plan->currency ?? 'DOP',
                'locale' => 'es',
                'plan_id' => $plan->id,
                'subscription_plan' => $plan->slug,
                'is_active' => true,
                'settings' => [
                    'setup_completed' => false,
                    'delivery_enabled' => true,
                    'pickup_enabled' => true,
                ],
            ]);

            // Create admin user (password cast as 'hashed' on User model — no manual Hash::make)
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => 'admin',
                'tenant_id' => $tenant->id,
            ]);

            // Create subscription
            $isFreePlan = $plan->isFree();
            $periodEnd = $isFreePlan
                ? now()->addYear()
                : ($billingPeriod === 'annual' ? now()->addYear() : now()->addMonth());

            Subscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'status' => $isFreePlan ? 'active' : 'pending_payment',
                'billing_period' => $billingPeriod,
                'price' => $price,
                'current_period_start' => now(),
                'current_period_end' => $periodEnd,
            ]);

            // Login
            Auth::login($user);
            $request->session()->regenerate();

            // Free plan → setup wizard; paid plan → payment step
            return $isFreePlan ? redirect('/setup') : redirect('/register/payment');
        });
    }
}
