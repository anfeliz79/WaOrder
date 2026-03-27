<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = null;

        // From authenticated user's session
        if ($request->user()) {
            $tenant = $request->user()->tenant;
        }

        // From subdomain (e.g., pizzeria.waorder.com)
        if (!$tenant) {
            $host = $request->getHost();
            $parts = explode('.', $host);
            if (count($parts) > 2) {
                $slug = $parts[0];
                $tenant = Tenant::where('slug', $slug)->where('is_active', true)->first();
            }
        }

        // From Bearer token (API requests)
        if (!$tenant && $request->bearerToken()) {
            // Simple token-based tenant resolution for API
            // Token format: tenant_id:secret (Phase 2: proper API keys)
            $token = $request->bearerToken();
            $parts = explode(':', $token);
            if (count($parts) === 2) {
                $tenant = Tenant::where('id', $parts[0])->where('is_active', true)->first();
            }
        }

        if (!$tenant) {
            // For MVP: fallback to first active tenant if single-tenant mode
            $tenant = Tenant::where('is_active', true)->first();
        }

        if ($tenant) {
            app()->instance('tenant', $tenant);
        }

        return $next($request);
    }
}
