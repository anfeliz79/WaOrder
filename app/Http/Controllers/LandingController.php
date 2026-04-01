<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use Illuminate\Http\Request;

class LandingController extends Controller
{
    public function index()
    {
        if (auth()->check()) {
            $user = auth()->user();
            if (method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin()) {
                return redirect('/superadmin');
            }
            return redirect('/dashboard');
        }

        $plans = Plan::where('is_active', true)
            ->orderBy('sort_order')
            ->get();

        return view('landing', compact('plans'));
    }
}
