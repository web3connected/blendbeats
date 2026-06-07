<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class DjLoungeComment extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'dj_lounge_post_id',
        'user_id',
        'parent_id',
        'body',
        'status',
    ];

    /**
     * @return BelongsTo<DjLoungePost, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(DjLoungePost::class, 'dj_lounge_post_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<DjLoungeComment, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /**
     * @return HasMany<DjLoungeComment, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    /**
     * @return MorphMany<DjLoungeReaction, $this>
     */
    public function reactions(): MorphMany
    {
        return $this->morphMany(DjLoungeReaction::class, 'reactable');
    }
}
