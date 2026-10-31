<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'created_by', 'team_a_id', 'team_b_id', 'team_a_name', 'team_b_name',
    'ground', 'city', 'overs_limit', 'players_per_side', 'ball_type',
    'status', 'toss_winner', 'toss_decision', 'result',
    'stream_url', 'stream_key', 'stream_status', 'started_at', 'ended_at',
])]
class CricketMatch extends Model
{
    protected $table = 'matches';

    protected function casts(): array
    {
        return [
            'result' => 'array',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function players(): HasMany
    {
        return $this->hasMany(MatchPlayer::class, 'match_id');
    }

    public function innings(): HasMany
    {
        return $this->hasMany(Innings::class, 'match_id')->orderBy('number');
    }

    public function sidePlayers(string $side)
    {
        return $this->players()->where('side', $side)->orderBy('batting_order');
    }

    /** The team this match credits to side a|b, or null for pickup games. */
    public function sideTeamId(string $side): ?int
    {
        return $side === 'a' ? $this->team_a_id : $this->team_b_id;
    }

    public function sideName(string $side): string
    {
        return $side === 'a' ? $this->team_a_name : $this->team_b_name;
    }

    /**
     * Who may score this match: the creator, or anyone holding a
     * scorer/coach/admin/owner role in a linked app team.
     */
    public function canScore(User $user): bool
    {
        if ($user->id === $this->created_by || $user->hasRole('super-admin')) {
            return true;
        }

        $teamIds = array_filter([$this->team_a_id, $this->team_b_id]);
        if (! $teamIds) {
            return false;
        }

        return TeamMember::whereIn('team_id', $teamIds)
            ->where('user_id', $user->id)
            ->whereIn('role', ['owner', 'admin', 'coach', 'scorer'])
            ->exists();
    }
}
