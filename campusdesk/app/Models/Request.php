<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Request extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';
    public const STATUS_PENDING = 'pending';
    public const STATUS_IN_REVIEW = 'in_review';
    public const STATUS_FORWARDED = 'forwarded';
    public const STATUS_READY = 'ready';
    public const STATUS_COLLECTED = 'collected';
    public const STATUS_REJECTED = 'rejected';

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function requestType(): BelongsTo
    {
        return $this->belongsTo(RequestType::class);
    }

    public function requestTypeTier(): BelongsTo
    {
        return $this->belongsTo(RequestTypeTier::class);
    }

    public function pendingUpgradeTier(): BelongsTo
    {
        return $this->belongsTo(RequestTypeTier::class, 'pending_upgrade_tier_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function requestStages(): HasMany
    {
        return $this->hasMany(RequestStage::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(Attachment::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(StatusHistory::class);
    }

    protected $fillable = [
        'request_type_id',
        'request_type_tier_id',
        'pending_upgrade_tier_id',
        'tier_name_snapshot',
        'fee_amount',
        'amount_paid',
        'currency',
        'turnaround_days_snapshot',
        'tier_priority',
        'paid_at',
        'target_ready_at',
        'description',
        'status',
        'is_reopened',
        'student_id',
    ];

    protected function casts(): array
    {
        return [
            'is_reopened' => 'boolean',
            'fee_amount' => 'integer',
            'amount_paid' => 'integer',
            'turnaround_days_snapshot' => 'integer',
            'tier_priority' => 'integer',
            'paid_at' => 'datetime',
            'target_ready_at' => 'datetime',
        ];
    }

    /**
     * Compute remaining amount due for this request in XAF.
     */
    public function amountDue(): int
    {
        $targetTier = $this->pendingUpgradeTier ?: $this->requestTypeTier;
        $fee = $targetTier ? (int) $targetTier->fee_amount : (int) $this->fee_amount;

        return max(0, $fee - (int) $this->amount_paid);
    }
}
