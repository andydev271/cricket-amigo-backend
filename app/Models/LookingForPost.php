<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'team_id', 'kind', 'city', 'text', 'status'])]
class LookingForPost extends Model
{
    public const KINDS = ['seeking_team', 'seeking_players', 'seeking_match', 'seeking_coach'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
