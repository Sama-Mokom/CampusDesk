<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attachment extends Model
{
    //
      public function request(): BelongsTo
    {
        return $this->belongsTo(Request::class);
    }
}
