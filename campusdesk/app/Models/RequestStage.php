<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;


class RequestStage extends Model
{
    //
    public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }
     public function department(): BelongsTo
    {
        return $this->belongsTo(department::class);
    }
     public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
       public function statusHistories(): HasMany
    {
        return $this->hasMany(StatusHistory::class);
    }
    protected $fillable = [
        'department_id',
        'sequence_order',
    ];
}
