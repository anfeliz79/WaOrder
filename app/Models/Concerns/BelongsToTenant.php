<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToTenant
{
    protected static function bootBelongsToTenant(): void
    {
        static::creating(function ($model) {
            if (app()->bound('tenant') && app('tenant')) {
                $model->tenant_id = app('tenant')->id;
            }
        });

        static::addGlobalScope('tenant', function (Builder $builder) {
            // SuperAdmin bypasses tenant isolation
            // Use session guard's raw user to avoid infinite recursion
            // (auth()->user() triggers this scope again on User model → OOM)
            $guard = auth()->guard();
            if ($guard->hasUser()) {
                $authUser = $guard->user();
                // Only User models have isSuperAdmin() — Driver model does not.
                // Calling isSuperAdmin() on a Driver instance throws BadMethodCallException.
                if ($authUser instanceof \App\Models\User && $authUser->isSuperAdmin()) {
                    return;
                }
            }
            if (app()->bound('tenant') && app('tenant')) {
                $builder->where($builder->getModel()->getTable() . '.tenant_id', app('tenant')->id);
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
