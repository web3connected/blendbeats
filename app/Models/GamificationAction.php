<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GamificationAction extends Model
{
    protected $fillable = [
        'action_key',
        'label',
        'description',
        'role_context',
        'xp_amount',
        'daily_limit',
        'weekly_limit',
        'once_per_target',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'once_per_target' => 'boolean',
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];
}
