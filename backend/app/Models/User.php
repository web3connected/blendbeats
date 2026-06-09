<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Traits\AvatarTrait;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'first_name', 'last_name', 'email', 'password', 'avatar', 'is_gravatar', 'use_gravatar', 'media_storage_tier'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use AvatarTrait, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_gravatar' => 'boolean',
            'use_gravatar' => 'boolean',
        ];
    }

    /**
     * @return HasOne<UserProfile, $this>
     */
    public function profile(): HasOne
    {
        return $this->hasOne(UserProfile::class);
    }

    /**
     * @return HasOne<DjProfile, $this>
     */
    public function djProfile(): HasOne
    {
        return $this->hasOne(DjProfile::class);
    }

    /**
     * @return HasMany<MediaFile, $this>
     */
    public function mediaFiles(): HasMany
    {
        return $this->hasMany(MediaFile::class);
    }

    /**
     * @return HasOne<MediaAccount, $this>
     */
    public function mediaAccount(): HasOne
    {
        return $this->hasOne(MediaAccount::class);
    }

    /**
     * @return HasMany<UserFeatureActivation, $this>
     */
    public function featureActivations(): HasMany
    {
        return $this->hasMany(UserFeatureActivation::class);
    }

    /**
     * @return HasMany<Follower, $this>
     */
    public function followedDjs(): HasMany
    {
        return $this->hasMany(Follower::class, 'follower_user_id');
    }
}
