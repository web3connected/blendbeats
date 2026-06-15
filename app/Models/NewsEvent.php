<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class NewsEvent extends Model
{
    protected $fillable = [
        'title',
        'slug',
        'description',
        'event_type',
        'status',
        'started_at',
        'ended_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (NewsEvent $event): void {
            if (! $event->slug) {
                $event->slug = Str::slug($event->title);
            }
        });
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(NewsMilestone::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeType(Builder $query, string $type): Builder
    {
        return $query->where('event_type', $type);
    }
}
