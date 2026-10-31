<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'from_team_id', 'to_team_id', 'created_by', 'match_id',
    'message', 'proposed_at', 'status',
])]
class MatchRequest extends Model
{
    protected function casts(): array
    {
        return ['proposed_at' => 'datetime'];
    }

    public function fromTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'from_team_id');
    }

    public function toTeam(): BelongsTo
    {
        return $this->belongsTo(Team::class, 'to_team_id');
    }
}
