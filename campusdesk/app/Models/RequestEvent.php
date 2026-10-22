<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequestEvent extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'request_id',
        'event_name',
        'schema_version',
        'aggregate_sequence',
        'delivery_status',
        'payload',
        'audiences',
        'attempt_count',
        'last_attempt_at',
        'published_at',
        'failed_at',
        'last_error',
    ];

    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }

    protected function casts(): array
    {
        return [
            'schema_version' => 'integer',
            'aggregate_sequence' => 'integer',
            'payload' => 'array',
            'audiences' => 'array',
            'attempt_count' => 'integer',
            'last_attempt_at' => 'datetime',
            'published_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }
}
