<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Follower extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'follower_user_id',
        'followed_dj_id',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function follower(): BelongsTo
    {
        return $this->belongsTo(User::class, 'follower_user_id');
    }

    /**
     * @return BelongsTo<DjProfile, $this>
     */
    public function followedDj(): BelongsTo
    {
        return $this->belongsTo(DjProfile::class, 'followed_dj_id');
    }
}
