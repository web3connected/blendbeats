<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DjBattleVote extends Model
{
    protected $fillable = [
        'battle_id',
        'user_id',
        'prediction_dj_profile_id',
        'vote_weight',
        'reward_eligible',
        'watched_challenger_at',
        'watched_opponent_at',
        'submitted_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'vote_weight' => 'integer',
            'reward_eligible' => 'boolean',
            'watched_challenger_at' => 'datetime',
            'watched_opponent_at' => 'datetime',
            'submitted_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function battle(): BelongsTo
    {
        return $this->belongsTo(DjBattle::class, 'battle_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function prediction(): BelongsTo
    {
        return $this->belongsTo(DjProfile::class, 'prediction_dj_profile_id');
    }

    public function scores(): HasMany
    {
        return $this->hasMany(DjBattleVoteScore::class, 'vote_id');
    }
}
