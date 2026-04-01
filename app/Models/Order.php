<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use BelongsToTenant;

    const STATUS_CONFIRMED = 'confirmed';
    const STATUS_IN_PREPARATION = 'in_preparation';
    const STATUS_READY = 'ready';
    const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';
    const STATUS_DELIVERED = 'delivered';
    const STATUS_CANCELLED = 'cancelled';

    const TERMINAL_STATUSES = [self::STATUS_DELIVERED, self::STATUS_CANCELLED];

    protected $fillable = [
        'tenant_id', 'branch_id', 'customer_id', 'driver_id', 'order_number', 'status', 'delivery_type',
        'delivery_address', 'delivery_latitude', 'delivery_longitude',
        'customer_name', 'customer_phone', 'payment_method',
        'subtotal', 'delivery_fee', 'tax', 'total', 'notes',
        'estimated_ready_at', 'confirmed_at', 'completed_at',
        'cancelled_at', 'cancellation_reason',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'tax' => 'decimal:2',
        'total' => 'decimal:2',
        'estimated_ready_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->orderBy('created_at');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function cardnetPaymentSession(): HasOne
    {
        return $this->hasOne(CardnetPaymentSession::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES);
    }

    public function isActive(): bool
    {
        return !$this->isTerminal();
    }

    public function scopeActive($query)
    {
        return $query->whereNotIn('status', self::TERMINAL_STATUSES);
    }

    public function scopeByStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
