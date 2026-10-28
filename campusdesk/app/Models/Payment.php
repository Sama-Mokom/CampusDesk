<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_SUCCESSFUL = 'successful';
    public const STATUS_FAILED = 'failed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'request_id',
        'request_type_tier_id',
        'external_id',
        'provider',
        'provider_transaction_id',
        'payment_type',
        'checkout_mode',
        'payment_method',
        'payer_phone',
        'amount',
        'currency',
        'status',
        'checkout_url',
        'failure_reason',
        'raw_last_payload',
        'initiated_at',
        'completed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'raw_last_payload' => 'array',
            'initiated_at' => 'datetime',
            'completed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    public function requestTypeTier(): BelongsTo
    {
        return $this->belongsTo(RequestTypeTier::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [
            self::STATUS_SUCCESSFUL,
            self::STATUS_FAILED,
            self::STATUS_EXPIRED,
            self::STATUS_CANCELLED,
        ], true);
    }

    public function isSuccessful(): bool
    {
        return $this->status === self::STATUS_SUCCESSFUL;
    }
}
