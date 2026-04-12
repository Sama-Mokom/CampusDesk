<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentProfile extends Model
{
    //
     public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

     public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

     public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

     public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class);
    }
    protected $fillable = ['user_id'];
}
