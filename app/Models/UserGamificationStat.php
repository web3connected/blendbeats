<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserGamificationStat extends Model
{
    protected $fillable = [
        'user_id',
        'dj_xp',
        'fan_xp',
        'total_xp',
        'dj_level',
        'fan_level',
        'total_level',
        'dj_rank',
        'fan_rank',
        'last_activity_at',
    ];

    protected $casts = [
        'last_activity_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
