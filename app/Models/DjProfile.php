<?php

namespace App\Models;

use App\Traits\Rateable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class DjProfile extends Model
{
    use Rateable, SoftDeletes;

    protected $fillable = [
        'user_id',
        'dj_name',
        'handle',
        'profile_headline',
        'bio',
        'dj_type',
        'city',
        'state',
        'country',
        'lat',
        'lng',
        'booking_enabled',
        'battle_enabled',
        'profile_status',
        'visibility',
        'verification_status',
        'published_at',
        'view_count',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
            'booking_enabled' => 'boolean',
            'battle_enabled' => 'boolean',
            'published_at' => 'datetime',
            'view_count' => 'integer',
        ];
    }

    public function incrementViewCount(): void
    {
        $this->increment('view_count');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsToMany<DjGenre, $this>
     */
    public function genres(): BelongsToMany
    {
        return $this->belongsToMany(DjGenre::class, 'dj_profile_genres')
            ->using(DjProfileGenre::class)
            ->withPivot(['is_primary', 'sort_order'])
            ->withTimestamps()
            ->orderByPivot('is_primary', 'desc')
            ->orderByPivot('sort_order');
    }

    /**
     * @return HasOne<DjBookingSetting, $this>
     */
    public function bookingSetting(): HasOne
    {
        return $this->hasOne(DjBookingSetting::class);
    }

    /**
     * @return HasMany<DjSocialLink, $this>
     */
    public function socialLinks(): HasMany
    {
        return $this->hasMany(DjSocialLink::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<DjMedia, $this>
     */
    public function media(): HasMany
    {
        return $this->hasMany(DjMedia::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<Follower, $this>
     */
    public function followers(): HasMany
    {
        return $this->hasMany(Follower::class, 'followed_dj_id');
    }

    /**
     * @return HasMany<DjFeaturedStatus, $this>
     */
    public function featuredStatuses(): HasMany
    {
        return $this->hasMany(DjFeaturedStatus::class);
    }
}
