<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DjLoungePost extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'user_id',
        'body',
        'type',
        'status',
        'visibility',
        'genre',
        'media_title',
        'media_url',
        'media_meta',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return HasMany<DjLoungeComment, $this>
     */
    public function comments(): HasMany
    {
        return $this->hasMany(DjLoungeComment::class);
    }

    /**
     * @return MorphMany<DjLoungeReaction, $this>
     */
    public function reactions(): MorphMany
    {
        return $this->morphMany(DjLoungeReaction::class, 'reactable');
    }

    /**
     * @return HasMany<DjLoungeRepost, $this>
     */
    public function reposts(): HasMany
    {
        return $this->hasMany(DjLoungeRepost::class);
    }

    /**
     * @return HasMany<DjLoungeBookmark, $this>
     */
    public function bookmarks(): HasMany
    {
        return $this->hasMany(DjLoungeBookmark::class);
    }

    protected function isLive(): Attribute
    {
        return Attribute::get(fn (): bool => $this->type === 'battle_callout');
    }
}
