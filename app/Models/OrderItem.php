<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'order_id', 'menu_item_id', 'name', 'quantity', 'unit_price', 'modifiers', 'subtotal',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'modifiers' => 'array',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function menuItem(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class);
    }

    public function getModifiersSummary(): string
    {
        $parts = [];

        foreach ($this->modifiers['variants'] ?? [] as $groupName => $selection) {
            $parts[] = $selection['name'];
        }

        foreach ($this->modifiers['optionals'] ?? [] as $opt) {
            $parts[] = $opt['name'];
        }

        return implode(', ', $parts);
    }
}
