<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Badge extends Model
{
    protected $fillable = [
        'badge_key',
        'name',
        'description',
        'role_context',
        'icon',
        'rarity',
        'unlock_action_key',
        'unlock_threshold',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'metadata' => 'array',
    ];

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'user_badges')
            ->withPivot([
                'unlocked_at',
                'metadata',
            ])
            ->withTimestamps();
    }
}
