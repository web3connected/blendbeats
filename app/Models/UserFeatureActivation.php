<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserFeatureActivation extends Model
{
    public const MEDIA_LIBRARY = 'media_library';

    public const DJ_PROFILE = 'dj_profile';

    public const DJ_PORTFOLIO = 'dj_portfolio';

    public const DJ_LOUNGE = 'dj_lounge';

    public const BOOKING_PROFILE = 'booking_profile';

    protected $fillable = [
        'user_id',
        'admin_id',
        'feature_key',
        'status',
        'source',
        'metadata',
        'activated_at',
        'paused_at',
        'disabled_at',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'activated_at' => 'datetime',
            'paused_at' => 'datetime',
            'disabled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function admin(): BelongsTo
    {
        return $this->belongsTo(Admin::class);
    }
}
