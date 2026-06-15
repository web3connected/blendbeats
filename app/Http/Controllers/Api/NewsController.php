<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\NewsTrendingMetric;
use App\Models\Post;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class NewsController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $attributes = $request->validate([
            'search' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'tag' => ['nullable', 'string', 'max:255'],
            'featured' => ['nullable', 'boolean'],
            'breaking' => ['nullable', 'boolean'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:24'],
        ]);

        $posts = $this->baseNewsQuery()
            ->when($attributes['search'] ?? null, function (Builder $query, string $search): void {
                $query->where(function (Builder $searchQuery) use ($search): void {
                    $searchQuery
                        ->where('title', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                });
            })
            ->when($attributes['category'] ?? null, function (Builder $query, string $category): void {
                $query->where(function (Builder $categoryQuery) use ($category): void {
                    $categoryQuery
                        ->whereHas('primaryCategory', fn (Builder $primaryQuery) => $primaryQuery->where('slug', $category))
                        ->orWhereHas('categories', fn (Builder $pivotQuery) => $pivotQuery->where('slug', $category));
                });
            })
            ->when($attributes['tag'] ?? null, fn (Builder $query, string $tag) => $query->whereHas('tags', fn (Builder $tagQuery) => $tagQuery->where('slug', $tag)))
            ->when($request->boolean('featured'), fn (Builder $query) => $query->featured())
            ->when($request->boolean('breaking'), fn (Builder $query) => $query->breaking())
            ->latestPublished()
            ->paginate((int) ($attributes['limit'] ?? 12));

        return response()->json([
            'articles' => $posts->getCollection()->map(fn (Post $post): array => $this->postPayload($post))->values(),
            'pagination' => $this->paginationPayload($posts),
            'featured' => $this->featuredStories(),
            'breaking' => $this->breakingStories(),
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $post = $this->baseNewsQuery()
            ->where('slug', $slug)
            ->firstOrFail();

        $metric = $post->incrementViewCount();

        return response()->json([
            'article' => $this->postPayload($post->refresh(), true, $metric),
            'related' => $post->relatedStories()
                ->news()
                ->published()
                ->latestPublished()
                ->limit(4)
                ->get()
                ->map(fn (Post $relatedPost): array => $this->postPayload($relatedPost))
                ->values(),
        ]);
    }

    public function categories(): JsonResponse
    {
        $categories = Category::query()
            ->news()
            ->active()
            ->withCount(['posts' => fn (Builder $query) => $query->news()->published()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json([
            'categories' => $categories->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
                'posts_count' => $category->posts_count,
            ])->values(),
        ]);
    }

    public function category(string $slug, Request $request): JsonResponse
    {
        $category = Category::query()->news()->active()->where('slug', $slug)->firstOrFail();
        $limit = min(max((int) $request->query('limit', 12), 1), 24);

        $posts = $this->baseNewsQuery()
            ->where(function (Builder $query) use ($category): void {
                $query
                    ->where('category_id', $category->id)
                    ->orWhereHas('categories', fn (Builder $categoryQuery) => $categoryQuery->whereKey($category->id));
            })
            ->latestPublished()
            ->paginate($limit);

        return response()->json([
            'category' => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'description' => $category->description,
            ],
            'articles' => $posts->getCollection()->map(fn (Post $post): array => $this->postPayload($post))->values(),
            'pagination' => $this->paginationPayload($posts),
        ]);
    }

    private function baseNewsQuery(): Builder
    {
        return Post::query()
            ->news()
            ->published()
            ->with([
                'author:id,name,email',
                'primaryCategory:id,name,slug,group',
                'categories:id,name,slug,group',
                'tags:id,name,slug,type,group',
                'newsSource:id,name,slug,url,source_type',
                'newsEvent:id,title,slug,event_type,status',
                'trendingMetric',
            ]);
    }

    private function featuredStories(): array
    {
        return $this->baseNewsQuery()
            ->featured()
            ->latestPublished()
            ->limit(4)
            ->get()
            ->map(fn (Post $post): array => $this->postPayload($post))
            ->values()
            ->all();
    }

    private function breakingStories(): array
    {
        return $this->baseNewsQuery()
            ->breaking()
            ->latestPublished()
            ->limit(3)
            ->get()
            ->map(fn (Post $post): array => $this->postPayload($post))
            ->values()
            ->all();
    }

    private function postPayload(Post $post, bool $includeContent = false, ?NewsTrendingMetric $metric = null): array
    {
        $metric ??= $post->trendingMetric;

        return [
            'id' => $post->id,
            'title' => $post->title,
            'slug' => $post->slug,
            'excerpt' => $post->excerpt,
            'content' => $includeContent ? $post->content : null,
            'status' => $post->status,
            'is_verified' => $post->is_verified,
            'verification_status' => $post->verification_status,
            'is_breaking' => $post->is_breaking,
            'is_featured' => $post->is_featured,
            'importance_level' => $post->importance_level,
            'featured_image' => $post->featured_image,
            'seo' => $post->seo,
            'published_at' => $post->published_at?->toISOString(),
            'author' => $post->author ? [
                'id' => $post->author->id,
                'name' => $post->author->name,
            ] : null,
            'category' => $post->primaryCategory ? [
                'id' => $post->primaryCategory->id,
                'name' => $post->primaryCategory->name,
                'slug' => $post->primaryCategory->slug,
            ] : null,
            'categories' => $post->categories->map(fn (Category $category): array => [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
            ])->values(),
            'tags' => $post->tags->map(fn ($tag): array => [
                'id' => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
                'type' => $tag->type,
            ])->values(),
            'source' => $post->newsSource ? [
                'id' => $post->newsSource->id,
                'name' => $post->newsSource->name,
                'slug' => $post->newsSource->slug,
                'url' => $post->newsSource->url,
                'source_type' => $post->newsSource->source_type,
            ] : null,
            'event' => $post->newsEvent ? [
                'id' => $post->newsEvent->id,
                'title' => $post->newsEvent->title,
                'slug' => $post->newsEvent->slug,
                'event_type' => $post->newsEvent->event_type,
            ] : null,
            'metrics' => [
                'views' => (int) ($metric?->views ?? 0),
                'shares' => (int) ($metric?->shares ?? 0),
                'comments_count' => (int) ($metric?->comments_count ?? 0),
                'engagement_score' => (int) ($metric?->engagement_score ?? 0),
            ],
        ];
    }

    private function paginationPayload(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
            'last_page' => $paginator->lastPage(),
        ];
    }
}
