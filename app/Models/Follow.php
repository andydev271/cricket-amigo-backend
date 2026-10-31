<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['user_id', 'followable_type', 'followable_id'])]
class Follow extends Model
{
    public function followable(): MorphTo
    {
        return $this->morphTo();
    }
}
