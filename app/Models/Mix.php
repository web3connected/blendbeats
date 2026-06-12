<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Mix extends Model
{
    protected $fillable = [
        'user_id',
        'audio_media_file_id',
        'cover_media_file_id',
        'title',
        'slug',
        'description',
        'genre',
        'audio_file',
        'cover_image',
        'duration',
        'is_public',
        'is_featured',
        'play_count',
        'rating_average',
        'rating_count',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'duration' => 'integer',
            'is_public' => 'boolean',
            'is_featured' => 'boolean',
            'play_count' => 'integer',
            'rating_average' => 'decimal:2',
            'rating_count' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Mix $mix): void {
            if (! $mix->slug) {
                $mix->slug = static::uniqueSlug($mix->title);
            }

            if ($mix->is_public && ! $mix->published_at) {
                $mix->published_at = now();
            }
        });

        static::saving(function (Mix $mix): void {
            if ($mix->isDirty('is_public') && $mix->is_public && ! $mix->published_at) {
                $mix->published_at = now();
            }
        });
    }

    public function scopePublic(Builder $query): Builder
    {
        return $query
            ->where('is_public', true)
            ->whereNotNull('published_at');
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query
            ->public()
            ->where('is_featured', true);
    }

    public function scopeByGenre(Builder $query, string $genre): Builder
    {
        return $query->where('genre', $genre);
    }

    public function scopeLatestPublished(Builder $query): Builder
    {
        return $query
            ->orderByDesc('published_at')
            ->orderByDesc('created_at');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function audioMediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'audio_media_file_id');
    }

    public function coverMediaFile(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'cover_media_file_id');
    }

    public function incrementPlayCount(): void
    {
        $this->increment('play_count');
    }

    public function getDjNameAttribute(): string
    {
        return $this->user?->name ?: 'BlendBeats DJ';
    }

    public function getAudioUrlAttribute(): ?string
    {
        return $this->publicUrl($this->audio_file) ?? $this->audioMediaFile?->url;
    }

    public function getCoverImageUrlAttribute(): ?string
    {
        return $this->publicUrl($this->cover_image) ?? $this->coverMediaFile?->url;
    }

    private function publicUrl(?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '/'])) {
            return $path;
        }

        if (Str::startsWith($path, 'media/')) {
            return asset($path);
        }

        return Storage::disk('public')->url($path);
    }

    private static function uniqueSlug(string $title): string
    {
        $base = Str::slug($title) ?: 'mix';
        $slug = $base;
        $index = 2;

        while (static::query()->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$index}";
            $index++;
        }

        return $slug;
    }
}
