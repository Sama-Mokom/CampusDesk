<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    // Reference data; no public department write endpoint exists.
    protected $fillable = ['faculty_id', 'name', 'code', 'type'];

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
        // The pivot has Laravel timestamps.  Keep this in sync with the migration so
        // relationship helpers such as attach() and syncWithoutDetaching() work in
        // Tinker as well as in seeders.
        return $this->belongsToMany(StaffProfile::class, 'department_staff')
            ->withPivot('is_primary')
            ->withTimestamps();
    }
}
