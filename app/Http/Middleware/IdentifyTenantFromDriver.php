<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class IdentifyTenantFromDriver
{
    public function handle(Request $request, Closure $next): Response
    {
        $driver = $request->user('driver');

        if (!$driver) {
            return response()->json(['message' => 'Unauthenticated'], 401);
        }

        $tenant = Tenant::where('id', $driver->tenant_id)
            ->where('is_active', true)
            ->first();

        if (!$tenant) {
            return response()->json(['message' => 'Tenant not found'], 404);
        }

        app()->instance('tenant', $tenant);

        return $next($request);
    }
}
