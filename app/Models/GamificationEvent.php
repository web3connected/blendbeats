<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GamificationEvent extends Model
{
    protected $fillable = [
        'user_id',
        'action_key',
        'role_context',
        'xp_awarded',
        'target_type',
        'target_id',
        'event_hash',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
