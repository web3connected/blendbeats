<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsMilestone extends Model
{
    protected $fillable = [
        'news_event_id',
        'post_id',
        'title',
        'description',
        'milestone_type',
        'occurred_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(NewsEvent::class, 'news_event_id');
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function scopeType(Builder $query, string $type): Builder
    {
        return $query->where('milestone_type', $type);
    }
}
