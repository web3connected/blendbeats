<?php

namespace App\Models;

use App\Traits\Rateable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Post extends Model
{
    use Rateable, SoftDeletes;

    public const TYPE_BLOG = 'blog';
    public const TYPE_NEWS = 'news';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_REVIEW = 'review';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'author_id',
        'category_id',
        'news_source_id',
        'news_event_id',
        'content_type',
        'title',
        'slug',
        'excerpt',
        'content',
        'status',
        'is_verified',
        'verification_status',
        'is_breaking',
        'is_featured',
        'importance_level',
        'featured_image',
        'seo',
        'metadata',
        'reviewed_at',
        'approved_at',
        'published_at',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'is_verified' => 'boolean',
            'is_breaking' => 'boolean',
            'is_featured' => 'boolean',
            'importance_level' => 'integer',
            'featured_image' => 'array',
            'seo' => 'array',
            'metadata' => 'array',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Post $post): void {
            if (! $post->slug) {
                $post->slug = Str::slug($post->title);
            }
        });
    }

    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function primaryCategory(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'category_id');
    }

    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(Category::class, 'category_post')->withTimestamps();
    }

    public function tags(): MorphToMany
    {
        return $this->morphToMany(Tag::class, 'taggable')->withTimestamps();
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    public function approvedComments(): HasMany
    {
        return $this->comments()->where('status', Comment::STATUS_APPROVED);
    }

    public function newsSource(): BelongsTo
    {
        return $this->belongsTo(NewsSource::class);
    }

    public function newsEvent(): BelongsTo
    {
        return $this->belongsTo(NewsEvent::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(NewsMilestone::class);
    }

    public function storyRelations(): HasMany
    {
        return $this->hasMany(NewsStoryRelation::class);
    }

    public function relatedStories(): BelongsToMany
    {
        return $this->belongsToMany(
            Post::class,
            'news_story_relations',
            'post_id',
            'related_post_id',
        )->withPivot(['relation_type', 'metadata'])->withTimestamps();
    }

    public function trendingMetric(): HasOne
    {
        return $this->hasOne(NewsTrendingMetric::class)->latestOfMany();
    }

    public function trendingMetrics(): HasMany
    {
        return $this->hasMany(NewsTrendingMetric::class);
    }

    public function scopeBlog(Builder $query): Builder
    {
        return $query->where('content_type', self::TYPE_BLOG);
    }

    public function scopeNews(Builder $query): Builder
    {
        return $query->where('content_type', self::TYPE_NEWS);
    }

    public function scopeContentType(Builder $query, string $contentType): Builder
    {
        return $query->where('content_type', $contentType);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PUBLISHED)->whereNotNull('published_at');
    }

    public function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_DRAFT);
    }

    public function scopeReview(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_REVIEW);
    }

    public function scopeFeatured(Builder $query): Builder
    {
        return $query->where('is_featured', true);
    }

    public function scopeBreaking(Builder $query): Builder
    {
        return $query->where('is_breaking', true);
    }

    public function scopeVerified(Builder $query): Builder
    {
        return $query->where('is_verified', true);
    }

    public function scopeLatestPublished(Builder $query): Builder
    {
        return $query->published()->orderByDesc('published_at');
    }

    public function isNews(): bool
    {
        return $this->content_type === self::TYPE_NEWS;
    }

    public function isPublished(): bool
    {
        return $this->status === self::STATUS_PUBLISHED && $this->published_at !== null;
    }
}
