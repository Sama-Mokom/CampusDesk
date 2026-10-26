<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Request extends Model
{
    use HasFactory;

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
}
