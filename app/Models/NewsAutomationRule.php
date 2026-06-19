<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class NewsAutomationRule extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'rule_type',
        'event_type',
        'milestone_key',
        'source_type',
        'source_id',
        'condition_field',
        'condition_operator',
        'condition_value',
        'cooldown_minutes',
        'priority',
        'is_active',
        'metadata',
        'last_checked_at',
        'last_triggered_at',
    ];

    protected function casts(): array
    {
        return [
            'source_id' => 'integer',
            'cooldown_minutes' => 'integer',
            'priority' => 'integer',
            'is_active' => 'boolean',
            'metadata' => 'array',
            'last_checked_at' => 'datetime',
            'last_triggered_at' => 'datetime',
        ];
    }

    public function logs(): HasMany
    {
        return $this->hasMany(NewsAutomationLog::class, 'rule_id');
    }

    public function processedMilestones(): HasMany
    {
        return $this->hasMany(NewsAutomationProcessedMilestone::class, 'rule_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeType(Builder $query, string $type): Builder
    {
        return $query->where('rule_type', $type);
    }
}
