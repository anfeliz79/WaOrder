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

    public function notices(): HasMany
    {
        return $this->hasMany(TenantNotice::class);
    }

    public function cardnetTokens(): HasMany
    {
        return $this->hasMany(CardnetToken::class);
    }

    public function defaultCardnetToken(): HasOne
    {
        return $this->hasOne(CardnetToken::class)->where('is_default', true)->where('is_active', true);
    }

    public function getSurveyQuestions(): array
    {
        $questions = data_get($this->settings, 'survey.questions');

        if (empty($questions)) {
            return self::defaultSurveyQuestions();
        }

        return array_values(array_filter($questions, fn($q) => $q['enabled'] ?? true));
    }

    public static function defaultSurveyQuestions(): array
    {
        return [
            [
                'key' => 'rating',
                'label' => '¿Cómo calificarías tu experiencia de hoy?',
                'type' => 'rating',
                'enabled' => true,
                'options' => [
                    ['id' => 'rate_5', 'title' => '⭐⭐⭐⭐⭐ (5)'],
                    ['id' => 'rate_4', 'title' => '⭐⭐⭐⭐ (4)'],
                    ['id' => 'rate_3', 'title' => '⭐⭐⭐ (3 o menos)'],
                ],
            ],
            [
                'key' => 'food_quality',
                'label' => '¿Cómo estuvo la calidad de la comida?',
                'type' => 'buttons',
                'enabled' => true,
                'options' => [
                    ['id' => 'food_excellent', 'title' => 'Excelente'],
                    ['id' => 'food_good', 'title' => 'Buena'],
                    ['id' => 'food_regular', 'title' => 'Regular'],
                ],
            ],
            [
                'key' => 'comment',
                'label' => '¿Tienes algún comentario adicional?',
                'type' => 'text',
                'enabled' => true,
                'options' => [],
            ],
        ];
    }

    public function isBotEnabled(): bool
    {
        $subscription = $this->subscription;

        if (!$subscription) {
            return false;
        }

        if ($subscription->isActive()) {
            return true;
        }

        // Past due but still inside grace period → keep bot running
        if ($subscription->isInGracePeriod()) {
            return true;
        }

        return false;
    }

    public function getSubscriptionAlert(): ?array
    {
        $subscription = $this->subscription;

        if (!$subscription) {
            return [
                'type'    => 'danger',
                'message' => 'No tienes un plan activo. Tu bot de WhatsApp está desactivado. Adquiere un plan para comenzar a recibir pedidos.',
                'link'    => '/billing',
            ];
        }

        if ($subscription->isActive()) {
            if ($subscription->isTrialing()) {
                $days = $subscription->trialDaysRemaining();
                if ($days <= 3) {
                    return [
                        'type'    => 'warning',
                        'message' => "Tu periodo de prueba vence en {$days} " . ($days === 1 ? 'día' : 'días') . '. Activa tu plan para no perder el servicio.',
                        'link'    => '/billing',
                    ];
                }
            }
            return null;
        }

        if ($subscription->isInGracePeriod()) {
            $days = max(0, (int) now()->diffInDays($subscription->grace_period_ends_at, false));
            return [
                'type'    => 'warning',
                'message' => "Tu pago falló. Tienes {$days} " . ($days === 1 ? 'día' : 'días') . ' de gracia antes de que se desactive tu bot. Actualiza tu método de pago.',
                'link'    => '/billing',
            ];
        }

        $messages = [
            'cancelled' => 'Tu suscripción fue cancelada. Tu bot de WhatsApp está desactivado. Reactiva tu plan para recibir pedidos.',
            'suspended'  => 'Tu suscripción fue suspendida. Tu bot de WhatsApp está desactivado. Contacta soporte o reactiva tu plan.',
            'expired'    => 'Tu suscripción venció. Tu bot de WhatsApp está desactivado. Renueva tu plan para continuar.',
            'past_due'   => 'Tu pago está vencido y el periodo de gracia expiró. Tu bot de WhatsApp está desactivado. Actualiza tu método de pago.',
        ];

        return [
            'type'    => 'danger',
            'message' => $messages[$subscription->status] ?? 'Tu suscripción no está activa. Tu bot de WhatsApp está desactivado.',
            'link'    => '/billing',
        ];
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
