<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Programme extends Model
{
    //
    protected $fillable = [
        'faculty_id',
        'name',
        'code',
        'degree_type'
    ];
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }
}
