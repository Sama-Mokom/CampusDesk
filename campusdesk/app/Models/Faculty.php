<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Faculty extends Model
{
    protected $fillable = [
        'name',
        'code',
        'matricule_prefix',
    ];

    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    public function programmes(): HasMany
    {
        return $this->hasMany(Programme::class);
    }
}
