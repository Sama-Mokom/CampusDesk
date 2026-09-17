<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestType extends Model
{
    /**
     * Request types are managed by trusted seed/admin workflows only. If a
     * public write endpoint is introduced, replace this with an allow-list.
     */
    protected $fillable = ['name', 'description', 'default_department_sequence'];

    public function requests(): HasMany
    {
        return $this->hasMany(Request::class);
    }

    protected function casts(): array
    {
        return [
            'default_department_sequence' => 'array',
        ];
    }
}
