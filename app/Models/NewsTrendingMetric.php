<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsTrendingMetric extends Model
{
    protected $fillable = [
        'post_id',
        'views',
        'shares',
        'comments_count',
        'engagement_score',
        'window_started_at',
        'window_ended_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'views' => 'integer',
            'shares' => 'integer',
            'comments_count' => 'integer',
            'engagement_score' => 'integer',
            'window_started_at' => 'datetime',
            'window_ended_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function scopeCurrent(Builder $query): Builder
    {
        return $query
            ->where(fn (Builder $windowQuery): Builder => $windowQuery->whereNull('window_started_at')->orWhere('window_started_at', '<=', now()))
            ->where(fn (Builder $windowQuery): Builder => $windowQuery->whereNull('window_ended_at')->orWhere('window_ended_at', '>=', now()));
    }
}
