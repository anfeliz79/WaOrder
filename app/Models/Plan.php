<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'price_monthly',
        'price_annual',
        'trial_days',
        'currency',
        'max_branches',
        'max_menu_items',
        'max_drivers',
        'max_orders_per_month',
        'max_users',
        'whatsapp_bot_enabled',
        'ai_enabled',
        'external_menu_enabled',
        'custom_domain',
        'support_addon_available',
        'support_addon_price',
        'delivery_app_addon_available',
        'delivery_app_addon_price',
        'is_active',
        'sort_order',
        'metadata',
        'paypal_plan_id',
    ];

    protected $casts = [
        'price_monthly' => 'decimal:2',
        'price_annual' => 'decimal:2',
        'trial_days' => 'integer',
        'max_branches' => 'integer',
        'max_menu_items' => 'integer',
        'max_drivers' => 'integer',
        'max_orders_per_month' => 'integer',
        'max_users' => 'integer',
        'whatsapp_bot_enabled' => 'boolean',
        'ai_enabled' => 'boolean',
        'external_menu_enabled' => 'boolean',
        'custom_domain' => 'boolean',
        'support_addon_available' => 'boolean',
        'support_addon_price' => 'decimal:2',
        'delivery_app_addon_available' => 'boolean',
        'delivery_app_addon_price' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
        'metadata' => 'array',
    ];

    public function tenants(): HasMany
    {
        return $this->hasMany(Tenant::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function isFree(): bool
    {
        return $this->price_monthly <= 0;
    }

    public function hasTrial(): bool
    {
        return $this->trial_days > 0;
    }

    public function getPriceForPeriod(string $period): float
    {
        return $period === 'annual' && $this->price_annual
            ? (float) $this->price_annual
            : (float) $this->price_monthly;
    }
}
