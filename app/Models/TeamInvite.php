<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

#[Fillable(['team_id', 'created_by', 'token', 'role', 'max_uses', 'uses', 'expires_at'])]
class TeamInvite extends Model
{
    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public static function mint(): self
    {
        return new static(['token' => Str::random(48)]);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function isUsable(): bool
    {
        if ($this->expires_at && $this->expires_at->isPast()) {
            return false;
        }

        return $this->max_uses === null || $this->uses < $this->max_uses;
    }

    public function redeem(User $user): TeamMember
    {
        $member = TeamMember::firstOrCreate(
            ['team_id' => $this->team_id, 'user_id' => $user->id],
            ['role' => $this->role],
        );

        $this->increment('uses');

        return $member;
    }
}
