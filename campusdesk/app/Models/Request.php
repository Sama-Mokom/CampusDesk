<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Request as DocumentRequest;
use App\Models\StatusHistory;

class Request extends Model
{
    //
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
     public function requestType(): BelongsTo
    {
        return $this->belongsTo(RequestType::class);
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
        'description',
        'status',
        'is_reopened',
        'student_id',  
    ];

     protected function casts(): array
    {
        return [
            'is_reopened' => 'boolean',
        ];
    }
}
