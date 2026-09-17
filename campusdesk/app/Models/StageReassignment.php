<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StageReassignment extends Model
{
    protected $fillable = ['request_stage_id', 'from_user_id', 'to_user_id', 'reassigned_by'];

    public function stage(): BelongsTo { return $this->belongsTo(RequestStage::class, 'request_stage_id'); }
    public function fromUser(): BelongsTo { return $this->belongsTo(User::class, 'from_user_id'); }
    public function toUser(): BelongsTo { return $this->belongsTo(User::class, 'to_user_id'); }
    public function reassignedBy(): BelongsTo { return $this->belongsTo(User::class, 'reassigned_by'); }
}
