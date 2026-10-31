<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'city', 'country', 'created_by'])]
class Team extends Model
{
    public const ROLES = ['owner', 'admin', 'coach', 'scorer', 'player', 'viewer'];

    public const INVITABLE_ROLES = ['admin', 'coach', 'scorer', 'player', 'viewer'];

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    public function invites(): HasMany
    {
        return $this->hasMany(TeamInvite::class);
    }

    /** The caller's team role, or null when not a member. */
    public function roleOf(User $user): ?string
    {
        return $this->memberships()->where('user_id', $user->id)->value('role');
    }

    public function isManager(User $user): bool
    {
        return in_array($this->roleOf($user), ['owner', 'admin'], true);
    }
}
