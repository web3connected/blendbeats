<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NewsStoryRelation extends Model
{
    protected $fillable = [
        'post_id',
        'related_post_id',
        'relation_type',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    public function relatedPost(): BelongsTo
    {
        return $this->belongsTo(Post::class, 'related_post_id');
    }

    public function scopeType(Builder $query, string $type): Builder
    {
        return $query->where('relation_type', $type);
    }
}
