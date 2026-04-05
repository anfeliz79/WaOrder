<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class TransferVerification extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'subscription_id',
        'bank_account_id',
        'amount',
        'reference_number',
        'evidence_path',
        'evidence_name',
        'status',
        'admin_notes',
        'verified_by',
        'verified_at',
        'deadline_at',
    ];

    protected $casts = [
        'amount'      => 'decimal:2',
        'verified_at' => 'datetime',
        'deadline_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function verifiedBy(): BelongsTo
    {
        // SuperAdmin users have tenant_id = NULL — bypass scope
        return $this->belongsTo(User::class, 'verified_by')->withoutGlobalScopes();
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired';
    }

    public function isOverDeadline(): bool
    {
        return $this->deadline_at && $this->deadline_at->isPast();
    }

    public function hoursUntilDeadline(): float
    {
        if (!$this->deadline_at || $this->deadline_at->isPast()) {
            return 0;
        }

        return round(now()->diffInMinutes($this->deadline_at) / 60, 1);
    }

    public function getEvidenceUrlAttribute(): ?string
    {
        if (!$this->evidence_path) {
            return null;
        }

        return Storage::url($this->evidence_path);
    }

    public function isImageEvidence(): bool
    {
        if (!$this->evidence_name) {
            return false;
        }

        $ext = strtolower(pathinfo($this->evidence_name, PATHINFO_EXTENSION));

        return in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif']);
    }
}
