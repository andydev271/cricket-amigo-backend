<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['match_id', 'side', 'user_id', 'name', 'is_captain', 'is_keeper', 'batting_order'])]
class MatchPlayer extends Model
{
    protected function casts(): array
    {
        return ['is_captain' => 'boolean', 'is_keeper' => 'boolean'];
    }

    public function match(): BelongsTo
    {
        return $this->belongsTo(CricketMatch::class, 'match_id');
    }
}
