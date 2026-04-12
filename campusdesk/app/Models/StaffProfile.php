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

     public function departmentStaff(): BelongsToMany
    {
        return $this->belongsToMany(DepartmentStaff::class);
    }
}
