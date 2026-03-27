<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Support\Str;

class Driver extends Authenticatable
{
    use BelongsToTenant, HasApiTokens;

    protected $fillable = [
        'tenant_id', 'branch_id', 'name', 'phone', 'vehicle_type', 'vehicle_plate',
        'is_active', 'is_available', 'completed_deliveries',
        'push_token', 'device_platform', 'linking_token',
        'linking_token_expires_at', 'linked_at', 'app_linked',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_available' => 'boolean',
        'app_linked' => 'boolean',
        'linked_at' => 'datetime',
        'linking_token_expires_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function activeOrders(): HasMany
    {
        return $this->orders()->whereIn('status', ['out_for_delivery']);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true)->where('is_active', true);
    }

    public function incrementDeliveries(): void
    {
        $this->increment('completed_deliveries');
    }

    public function generateLinkingToken(): string
    {
        $token = Str::random(48);

        $this->update([
            'linking_token' => $token,
            'linking_token_expires_at' => now()->addMinutes(15),
        ]);

        return $token;
    }

    public function clearLinkingToken(): void
    {
        $this->update([
            'linking_token' => null,
            'linking_token_expires_at' => null,
        ]);
    }

    public function isLinked(): bool
    {
        return $this->app_linked;
    }

    public function hasPushToken(): bool
    {
        return !empty($this->push_token);
    }
}
