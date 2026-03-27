<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use BelongsToTenant, Notifiable;

    protected $fillable = [
        'tenant_id', 'name', 'email', 'password', 'role', 'is_active', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'password' => 'hashed',
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class)->withTimestamps();
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isGestor(): bool
    {
        return $this->role === 'gestor';
    }

    /**
     * Check if user can access a specific branch.
     * Admins can access all branches within their tenant.
     */
    public function canAccessBranch(int $branchId): bool
    {
        if ($this->isAdmin()) {
            return Branch::where('id', $branchId)
                ->where('tenant_id', $this->tenant_id)
                ->exists();
        }

        return $this->branches()->where('branch_id', $branchId)->exists();
    }

    /**
     * Get accessible branch IDs for this user.
     */
    public function accessibleBranchIds(): array
    {
        if ($this->isAdmin()) {
            return Branch::where('tenant_id', $this->tenant_id)
                ->active()
                ->pluck('id')
                ->toArray();
        }

        return $this->branches()->pluck('branch_id')->toArray();
    }
}
