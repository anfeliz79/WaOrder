<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuItem extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id', 'category_id', 'name', 'description', 'price',
        'image_url', 'is_available', 'is_active', 'modifiers', 'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_available' => 'boolean',
        'is_active' => 'boolean',
        'modifiers' => 'array',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'category_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function hasVariants(): bool
    {
        return !empty($this->modifiers['variant_groups']);
    }

    public function hasOptionals(): bool
    {
        return !empty($this->modifiers['optional_groups']);
    }

    public function hasModifiers(): bool
    {
        return $this->hasVariants() || $this->hasOptionals();
    }

    public function getVariantGroups(): array
    {
        return $this->modifiers['variant_groups'] ?? [];
    }

    public function getOptionalGroups(): array
    {
        return $this->modifiers['optional_groups'] ?? [];
    }

    public function getPriceRange(): array
    {
        if (!$this->hasVariants()) {
            return ['min' => (float) $this->price, 'max' => (float) $this->price];
        }

        $prices = [];
        foreach ($this->getVariantGroups() as $group) {
            foreach ($group['options'] ?? [] as $option) {
                $prices[] = (float) ($option['price'] ?? 0);
            }
        }

        return $prices
            ? ['min' => min($prices), 'max' => max($prices)]
            : ['min' => (float) $this->price, 'max' => (float) $this->price];
    }
}
