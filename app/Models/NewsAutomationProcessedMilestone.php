<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsAutomationProcessedMilestone extends Model
{
    protected $fillable = [
        'rule_id',
        'event_id',
        'milestone_key',
        'source_type',
        'source_id',
        'post_id',
        'processed_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'rule_id' => 'integer',
            'event_id' => 'integer',
            'source_id' => 'integer',
            'post_id' => 'integer',
            'processed_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(NewsAutomationRule::class, 'rule_id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(NewsEvent::class, 'event_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }
}
