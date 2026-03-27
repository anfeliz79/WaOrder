<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        // Skip for setup routes, logout, and API routes
        if ($request->is('setup*') || $request->is('logout') || $request->is('api/*') || $request->is('login')) {
            return $next($request);
        }

        $tenant = app()->bound('tenant') ? app('tenant') : null;

        if ($tenant) {
            $setupCompleted = data_get($tenant->settings, 'setup_completed', false);

            if (!$setupCompleted) {
                return redirect('/setup');
            }
        }

        return $next($request);
    }
}
