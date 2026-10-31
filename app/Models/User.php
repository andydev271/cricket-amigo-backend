<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'username', 'email', 'password', 'locale', 'city', 'country', 'dominant_hand', 'sport_roles', 'interests', 'onboarded_at'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements HasMedia
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, InteractsWithMedia, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'onboarded_at' => 'datetime',
            'password' => 'hashed',
            'sport_roles' => 'array',
            'interests' => 'array',
            'credits' => 'integer',
        ];
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('avatar')->singleFile();
        $this->addMediaCollection('reports');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(256)
            ->height(256)
            ->performOnCollections('avatar')
            ->nonQueued();
    }

    public function devices(): HasMany
    {
        return $this->hasMany(Device::class);
    }

    public function friends(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'friendships', 'user_id', 'friend_id')->withTimestamps();
    }

    public function friendRequestsReceived(): HasMany
    {
        return $this->hasMany(FriendRequest::class, 'recipient_id');
    }

    public function friendRequestsSent(): HasMany
    {
        return $this->hasMany(FriendRequest::class, 'sender_id');
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function creditTransactions(): HasMany
    {
        return $this->hasMany(CreditTransaction::class);
    }

    public function clipUploads(): HasMany
    {
        return $this->hasMany(ClipUpload::class);
    }

    public function getAvatarUrlAttribute(): ?string
    {
        return $this->getFirstMediaUrl('avatar', 'thumb') ?: $this->getFirstMediaUrl('avatar') ?: null;
    }
}
