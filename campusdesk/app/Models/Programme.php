<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Programme extends Model
{
    // Referenced directly by UserFactory and StudentSeeder — never re-typed locally
    // in either place so the filter can never silently drift.
    public const UNDERGRADUATE_DEGREE_TYPES = ['BACHELOR', 'CERTIFICATE'];

    // Declared for completeness; currently has no consumer (platform is undergraduate-only).
    public const POSTGRADUATE_DEGREE_TYPES = ['MASTER', 'PHD'];

    protected $fillable = [
        'faculty_id',
        'department_id',
        'name',
        'code',
        'degree_type',
    ];

    // faculty_id is retained denormalization (avoids a join on faculty-scoped queries)
    // but is never independently trusted at write time — always derived from department_id
    // via this single enforcement point.
    protected static function booted(): void
    {
        static::creating(function (Programme $programme) {
            $programme->faculty_id = $programme->department->faculty_id;
        });

        static::updating(function (Programme $programme) {
            if ($programme->isDirty('department_id')) {
                $programme->faculty_id = $programme->department->faculty_id;
            }
        });
    }

    public function faculty(): BelongsTo
    {
        return $this->belongsTo(Faculty::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
