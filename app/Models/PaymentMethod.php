<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'description',
        'icon',
        'is_active',
        'config',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'config'    => 'encrypted:json',
    ];

    /**
     * Scope: only active methods, ordered by sort_order.
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order');
    }

    /**
     * Get a specific config value by key.
     */
    public function getConfig(string $key, mixed $default = null): mixed
    {
        $config = $this->config ?? [];

        return $config[$key] ?? $default;
    }
}
