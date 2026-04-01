<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    protected $fillable = [
        'name', 'slug', 'whatsapp_phone_number_id', 'whatsapp_business_account_id',
        'whatsapp_access_token', 'whatsapp_app_secret', 'ai_api_key', 'timezone', 'currency', 'locale', 'settings',
        'subscription_plan', 'subscription_expires_at', 'plan_id', 'is_active',
    ];

    protected $hidden = [
        'whatsapp_access_token', 'whatsapp_app_secret', 'ai_api_key',
    ];

    protected $casts = [
        'settings' => 'array',
        'whatsapp_access_token' => 'encrypted',
        'whatsapp_app_secret' => 'encrypted',
        'ai_api_key' => 'encrypted',
        'subscription_expires_at' => 'datetime',
        'is_active' => 'boolean',
    ];

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function menuCategories(): HasMany
    {
        return $this->hasMany(MenuCategory::class);
    }

    public function menuItems(): HasMany
    {
        return $this->hasMany(MenuItem::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function chatSessions(): HasMany
    {
        return $this->hasMany(ChatSession::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function cardnetTokens(): HasMany
    {
        return $this->hasMany(CardnetToken::class);
    }

    public function defaultCardnetToken(): HasOne
    {
        return $this->hasOne(CardnetToken::class)->where('is_default', true)->where('is_active', true);
    }

    public function isAiEnabled(): bool
    {
        return (bool) $this->getSetting('ai.enabled', false);
    }

    public function getAiProvider(): string
    {
        return $this->getSetting('ai.provider', config('ai.default_provider', 'groq'));
    }

    public function getAiModel(): ?string
    {
        return $this->getSetting('ai.model') ?: null;
    }

    public function getSetting(string $key, mixed $default = null): mixed
    {
        return data_get($this->settings, $key, $default);
    }

    public function getMenuSource(): string
    {
        return $this->getSetting('menu_source', 'internal');
    }

    public function getActiveTaxes(): array
    {
        $taxes = $this->getSetting('taxes', []);

        return array_filter($taxes, fn ($tax) => !empty($tax['enabled']));
    }

    public function getTotalTaxRate(): float
    {
        return array_sum(array_column($this->getActiveTaxes(), 'rate'));
    }

    public function applyTax(float $price): float
    {
        $rate = $this->getTotalTaxRate();

        return $rate > 0 ? round($price * (1 + $rate / 100), 2) : $price;
    }

    public function extractTax(float $priceWithTax): float
    {
        $rate = $this->getTotalTaxRate();

        return $rate > 0 ? round($priceWithTax - ($priceWithTax / (1 + $rate / 100)), 2) : 0;
    }
}
