<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'phone', 'name', 'default_address',
        'default_delivery_type', 'total_orders', 'total_spent',
        'is_blocked', 'blocked_reason', 'last_order_at',
    ];

    protected $casts = [
        'total_spent' => 'decimal:2',
        'is_blocked' => 'boolean',
        'last_order_at' => 'datetime',
    ];

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function chatSessions(): HasMany
    {
        return $this->hasMany(ChatSession::class);
    }

    public function surveyResponses(): HasMany
    {
        return $this->hasMany(SurveyResponse::class);
    }

    public function incrementOrderStats(float $amount): void
    {
        $this->increment('total_orders');
        $this->increment('total_spent', $amount);
        $this->update(['last_order_at' => now()]);
    }
}
