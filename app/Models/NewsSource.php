<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class NewsSource extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'url',
        'source_type',
        'credibility_rating',
        'is_active',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (NewsSource $source): void {
            if (! $source->slug) {
                $source->slug = Str::slug($source->name);
            }
        });
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeType(Builder $query, string $type): Builder
    {
        return $query->where('source_type', $type);
    }
}
