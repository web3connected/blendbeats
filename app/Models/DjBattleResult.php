<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DjBattleResult extends Model
{
    protected $fillable = [
        'battle_id',
        'winner_dj_profile_id',
        'challenger_score',
        'opponent_score',
        'total_votes',
        'total_vote_weight',
        'is_draw',
        'calculation_version',
        'score_snapshot',
        'calculated_at',
    ];

    protected function casts(): array
    {
        return [
            'challenger_score' => 'decimal:3',
            'opponent_score' => 'decimal:3',
            'total_votes' => 'integer',
            'total_vote_weight' => 'integer',
            'is_draw' => 'boolean',
            'score_snapshot' => 'array',
            'calculated_at' => 'datetime',
        ];
    }

    public function battle(): BelongsTo
    {
        return $this->belongsTo(DjBattle::class, 'battle_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(DjProfile::class, 'winner_dj_profile_id');
    }
}
