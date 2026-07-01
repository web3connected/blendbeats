<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DjBattleVoteScore extends Model
{
    protected $fillable = [
        'vote_id',
        'battle_id',
        'entry_id',
        'dj_profile_id',
        'sample_integration_score',
        'mixing_score',
        'scratching_score',
        'creativity_score',
        'track_selection_score',
        'blending_score',
        'technical_execution_score',
        'battle_composition_score',
        'entertainment_value_score',
        'overall_performance_score',
        'total_score',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'mixing_score' => 'integer',
            'scratching_score' => 'integer',
            'creativity_score' => 'integer',
            'track_selection_score' => 'integer',
            'sample_integration_score' => 'integer',
            'blending_score' => 'integer',
            'technical_execution_score' => 'integer',
            'battle_composition_score' => 'integer',
            'entertainment_value_score' => 'integer',
            'overall_performance_score' => 'integer',
            'total_score' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function vote(): BelongsTo
    {
        return $this->belongsTo(DjBattleVote::class, 'vote_id');
    }

    public function battle(): BelongsTo
    {
        return $this->belongsTo(DjBattle::class, 'battle_id');
    }

    public function entry(): BelongsTo
    {
        return $this->belongsTo(DjBattleEntry::class, 'entry_id');
    }

    public function djProfile(): BelongsTo
    {
        return $this->belongsTo(DjProfile::class);
    }
}
