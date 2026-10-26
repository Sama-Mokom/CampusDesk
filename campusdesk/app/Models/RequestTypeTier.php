<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestTypeTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'request_type_id',
        'code',
        'name',
        'description',
        'fee_amount',
        'currency',
        'turnaround_days',
        'priority',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'fee_amount' => 'integer',
            'turnaround_days' => 'integer',
            'priority' => 'integer',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function requestType(): BelongsTo
    {
        return $this->belongsTo(RequestType::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(Request::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order', 'asc')->orderBy('priority', 'asc');
    }
}
