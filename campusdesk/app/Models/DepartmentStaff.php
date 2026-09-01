<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartmentStaff extends Model
{
    //
     public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class);
    }

     public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
    protected $table = 'department_staff';

      protected $fillable = [
        'is_primary',
        ];
}
