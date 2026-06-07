<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DjLoungeRepost extends Model
{
    protected $fillable = [
        'dj_lounge_post_id',
        'user_id',
        'body',
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
}
