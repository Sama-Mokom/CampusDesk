<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StatusHistory extends Model
{
    //
     public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
     public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }
     public function requestStage(): BelongsTo
    {
        return $this->belongsTo(RequestStage::class);
    }
    protected $table = 'status_history';
     protected $fillable = [
        'new_status',
        'old_status',
        'changed_by',
        'request_id',
        'request_stage_id',
        'note',
    ];
}
