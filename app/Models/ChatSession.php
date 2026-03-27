<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChatSession extends Model
{
    use BelongsToTenant;

    protected $table = 'chat_sessions';

    protected $fillable = [
        'tenant_id', 'customer_phone', 'customer_id', 'conversation_state',
        'cart_data', 'collected_info', 'context_data', 'active_order_id',
        'status', 'message_count', 'expires_at',
    ];

    protected $casts = [
        'cart_data' => 'array',
        'collected_info' => 'array',
        'context_data' => 'array',
        'expires_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function activeOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'active_order_id');
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    public function extendExpiry(int $minutes = 30): void
    {
        $this->update([
            'expires_at' => now()->addMinutes($minutes),
        ]);
    }

    public function getCartItems(): array
    {
        return data_get($this->cart_data, 'items', []);
    }

    public function getCartTotal(): float
    {
        return (float) data_get($this->cart_data, 'total', 0);
    }
}
