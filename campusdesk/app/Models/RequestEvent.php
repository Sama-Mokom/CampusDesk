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
        ];
    }
}
