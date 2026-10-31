<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'created_by', 'name', 'city', 'format', 'overs_limit', 'ball_type',
    'max_teams', 'points_win', 'points_draw', 'status', 'starts_on', 'ends_on',
])]
class Tournament extends Model
{
    protected function casts(): array
    {
        return ['starts_on' => 'date', 'ends_on' => 'date'];
    }

    public function teams(): HasMany
    {
        return $this->hasMany(TournamentTeam::class)->where('status', 'registered');
    }

    public function fixtures(): HasMany
    {
        return $this->hasMany(TournamentMatch::class)->orderBy('fixture_order');
    }

    public function isOrganizer(User $user): bool
    {
        return $user->id === $this->created_by || $user->hasRole('super-admin');
    }
}
