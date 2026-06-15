<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphedByMany;
use Illuminate\Support\Str;

class Tag extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'type',
        'group',
        'description',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Tag $tag): void {
            if (! $tag->slug) {
                $tag->slug = Str::slug($tag->name);
            }
        });
    }

    public function posts(): MorphedByMany
    {
        return $this->morphedByMany(Post::class, 'taggable')->withTimestamps();
    }

    public function scopeGroup(Builder $query, string $group): Builder
    {
        return $query->where('group', $group);
    }

    public function scopeType(Builder $query, string $type): Builder
    {
        return $query->where('type', $type);
    }

    public function scopeNews(Builder $query): Builder
    {
        return $query->group('news');
    }
}
