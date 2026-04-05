<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    protected $fillable = [
        'bank_name',
        'account_holder_name',
        'account_number',
        'account_type',
        'currency',
        'instructions',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function transferVerifications(): HasMany
    {
        return $this->hasMany(TransferVerification::class);
    }

    public function getAccountTypeLabelAttribute(): string
    {
        return match ($this->account_type) {
            'savings'  => 'Ahorro',
            'checking' => 'Corriente',
            default    => $this->account_type,
        };
    }
}
