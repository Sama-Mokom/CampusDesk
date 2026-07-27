<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = [
        'faculty_id',
        'code',
        'name',
        'type',
    ];

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function programmes(): HasMany
    {
        return $this->hasMany(Programme::class);
    }

    public function requestStages(): HasMany
    {
        return $this->hasMany(RequestStage::class);
    }

    public function staffProfiles(): BelongsToMany
    {
        // Pivot table name must be explicit: Laravel's default alphabetical-pluralization
        // convention would guess 'department_staff_profile', not the actual 'department_staff'.
        //
        // NOTE: withTimestamps() is intentionally omitted — the department_staff pivot table
        // has no created_at / updated_at columns; calling withTimestamps() against a table
        // without those columns causes attach() to fail.
        return $this->belongsToMany(StaffProfile::class, 'department_staff')
                    ->withPivot('is_primary');
    }
}
