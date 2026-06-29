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
        'mixing_score',
        'scratching_score',
        'creativity_score',
        'track_selection_score',
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
