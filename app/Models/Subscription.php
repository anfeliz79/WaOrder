<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscription extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'plan_id',
        'status',
        'billing_period',
        'price',
        'trial_ends_at',
        'current_period_start',
        'current_period_end',
        'grace_period_ends_at',
        'cancelled_at',
        'cancellation_reason',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'trial_ends_at' => 'datetime',
        'current_period_start' => 'datetime',
        'current_period_end' => 'datetime',
        'grace_period_ends_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function addons(): HasMany
    {
        return $this->hasMany(SubscriptionAddon::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['active', 'trialing']);
    }

    public function isTrialing(): bool
    {
        return $this->status === 'trialing';
    }

    public function isPastDue(): bool
    {
        return $this->status === 'past_due';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function isSuspended(): bool
    {
        return $this->status === 'suspended';
    }

    public function isInGracePeriod(): bool
    {
        return $this->status === 'past_due'
            && $this->grace_period_ends_at
            && $this->grace_period_ends_at->isFuture();
    }

    public function trialDaysRemaining(): int
    {
        if (!$this->isTrialing() || !$this->trial_ends_at) {
            return 0;
        }

        return max(0, (int) now()->diffInDays($this->trial_ends_at, false));
    }
}
