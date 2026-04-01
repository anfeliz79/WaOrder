<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CardnetToken extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'cardnet_customer_id',
        'trx_token',
        'card_brand',
        'card_last_four',
        'card_expiry',
        'is_default',
        'is_active',
    ];

    protected $casts = [
        'trx_token' => 'encrypted',
        'is_default' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $hidden = [
        'trx_token',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function getDisplayName(): string
    {
        $brand = $this->card_brand ?? 'Tarjeta';
        $last4 = $this->card_last_four ?? '****';

        return "{$brand} terminada en {$last4}";
    }
}
