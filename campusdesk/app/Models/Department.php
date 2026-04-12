<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    //
     protected $fillable = [
        'faculty_id',
        'code',
    ];
    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }
    public function requestStages(): HasMany
    {
        return $this->hasMany(RequestStage::class);
    }

    public function staffProfiles(): BelongsToMany
    {
        return $this->belongsToMany(StaffProfile::class);
    }
}
