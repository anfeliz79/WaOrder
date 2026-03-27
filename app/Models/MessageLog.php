<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class MessageLog extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'message_log';

    protected $fillable = [
        'tenant_id', 'session_id', 'driver_id', 'direction', 'customer_phone',
        'message_type', 'content', 'meta_message_id',
        'ai_used', 'ai_model', 'ai_tokens_used', 'ai_cost_usd',
    ];

    protected $casts = [
        'ai_used' => 'boolean',
        'ai_cost_usd' => 'decimal:6',
        'created_at' => 'datetime',
    ];
}
