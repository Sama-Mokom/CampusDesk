<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RequestType extends Model
{
    //
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
