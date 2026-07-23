<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class StaffProfile extends Model
{
    //
     public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function departments(): BelongsToMany
{
    return $this->belongsToMany(Department::class, 'department_staff', 'staff_profile_id', 'department_id')
                ->withPivot('is_primary')
                ->withTimestamps();
}

     public function departmentStaff(): BelongsToMany
    {
        return $this->belongsToMany(DepartmentStaff::class);
    }

    protected $fillable =[
       'staff_id',
       'admin_level',
    ];
}
