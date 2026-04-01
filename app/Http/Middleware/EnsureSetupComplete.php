<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupComplete
{
    public function handle(Request $request, Closure $next): Response
    {
        // Skip for setup routes, logout, API routes, and superadmin routes
        if ($request->is('setup*') || $request->is('logout') || $request->is('api/*') || $request->is('login') || $request->is('superadmin*') || $request->is('console*')) {
            return $next($request);
        }

        // SuperAdmin bypasses setup check
        if ($request->user()?->isSuperAdmin()) {
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
