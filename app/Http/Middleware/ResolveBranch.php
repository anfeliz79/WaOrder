<?php

namespace App\Http\Middleware;

use App\Models\Branch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveBranch
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // SuperAdmin doesn't need branch resolution
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $tenant = app()->bound('tenant') ? app('tenant') : null;

        if (!$tenant) {
            return $next($request);
        }

        $branchId = session('current_branch_id');

        // Validate user has access to the stored branch
        if ($branchId && !$user->canAccessBranch($branchId)) {
            $branchId = null;
            session()->forget('current_branch_id');
        }

        // If no branch selected, auto-select first accessible
        if (!$branchId) {
            $branchIds = $user->accessibleBranchIds();
            $branchId = $branchIds[0] ?? null;

            if ($branchId) {
                session(['current_branch_id' => $branchId]);
            }
        }

        if ($branchId) {
            $branch = Branch::find($branchId);
            app()->instance('branch', $branch);
        }

        return $next($request);
    }
}
