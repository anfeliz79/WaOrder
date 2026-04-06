<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'order_id',
        'type',
        'status',
        'amount',
        'tax',
        'total',
        'currency',
        'description',
        'payment_method',
        'cardnet_purchase_id',
        'cardnet_response',
        'paid_at',
        'due_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'cardnet_response' => 'array',
        'metadata' => 'array',
        'paid_at' => 'datetime',
        'due_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }

    public function markAsPaid(?string $cardnetPurchaseId = null, ?array $cardnetResponse = null): void
    {
        $this->update([
            'status' => 'paid',
            'paid_at' => now(),
            'cardnet_purchase_id' => $cardnetPurchaseId ?? $this->cardnet_purchase_id,
            'cardnet_response' => $cardnetResponse ?? $this->cardnet_response,
        ]);
    }

    public function markAsFailed(?array $cardnetResponse = null): void
    {
        $this->update([
            'status' => 'failed',
            'cardnet_response' => $cardnetResponse ?? $this->cardnet_response,
        ]);
    }
}
