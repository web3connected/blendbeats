<?php

use App\Http\Controllers\AutomationProxyController;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Website Routes
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::any('/automation/{path?}', AutomationProxyController::class)
    ->where('path', '.*')
    ->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\PreventRequestForgery::class])
    ->name('automation.proxy');

Route::get('/news', function (Request $request) {
    $posts = Post::query()
        ->news()
        ->published()
        ->with([
            'author:id,name,email,avatar,use_gravatar,is_gravatar',
            'primaryCategory:id,name,slug',
            'categories:id,name,slug',
            'tags:id,name,slug',
            'newsSource:id,name,slug',
            'trendingMetric:id,post_id,views,comments_count,engagement_score',
        ])
        ->when($request->filled('category'), function ($query) use ($request): void {
            $query->whereHas('categories', fn ($categoryQuery) => $categoryQuery->where('slug', $request->string('category')));
        })
        ->when($request->filled('tag'), function ($query) use ($request): void {
            $query->whereHas('tags', fn ($tagQuery) => $tagQuery->where('slug', $request->string('tag')));
        })
        ->when($request->filled('search'), function ($query) use ($request): void {
            $search = $request->string('search');
            $query->where(function ($searchQuery) use ($search): void {
                $searchQuery
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('excerpt', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        })
        ->orderByDesc('is_breaking')
        ->orderByDesc('published_at')
        ->paginate(9)
        ->withQueryString();

    return view('news.index', [
        'posts' => $posts,
        'categories' => Category::query()
            ->news()
            ->active()
            ->withCount(['posts' => fn ($query) => $query->news()->published()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(),
        'recentPosts' => Post::query()
            ->news()
            ->published()
            ->select(['id', 'title', 'slug', 'published_at', 'is_breaking'])
            ->latestPublished()
            ->limit(5)
            ->get(),
        'tags' => Tag::query()
            ->news()
            ->withCount(['posts' => fn ($query) => $query->news()->published()])
            ->orderByDesc('posts_count')
            ->orderBy('name')
            ->limit(12)
            ->get(),
    ]);
})->name('news.index');

Route::get('/news/categories/{category:slug}', function (Category $category) {
    abort_unless($category->group === 'news' && $category->is_active, 404);

    $posts = Post::query()
        ->news()
        ->published()
        ->whereHas('categories', fn ($query) => $query->whereKey($category->id))
        ->with([
            'author:id,name,email,avatar,use_gravatar,is_gravatar',
            'primaryCategory:id,name,slug',
            'categories:id,name,slug',
            'tags:id,name,slug',
            'newsSource:id,name,slug',
            'trendingMetric:id,post_id,views,comments_count,engagement_score',
        ])
        ->orderByDesc('is_breaking')
        ->orderByDesc('published_at')
        ->paginate(9)
        ->withQueryString();

    return view('news.category', [
        'category' => $category,
        'posts' => $posts,
        'categories' => Category::query()
            ->news()
            ->active()
            ->withCount(['posts' => fn ($query) => $query->news()->published()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(),
        'recentPosts' => Post::query()
            ->news()
            ->published()
            ->select(['id', 'title', 'slug', 'published_at', 'is_breaking'])
            ->latestPublished()
            ->limit(5)
            ->get(),
        'tags' => Tag::query()
            ->news()
            ->withCount(['posts' => fn ($query) => $query->news()->published()])
            ->orderByDesc('posts_count')
            ->orderBy('name')
            ->limit(12)
            ->get(),
    ]);
})->name('news.categories.show');

Route::get('/news/{post:slug}', function (Post $post) {
    abort_unless($post->isNews() && $post->isPublished(), 404);

    $post->load([
        'author:id,name,email,avatar,use_gravatar,is_gravatar',
        'primaryCategory:id,name,slug',
        'categories:id,name,slug',
        'tags:id,name,slug',
        'newsSource:id,name,slug,url,source_type',
        'newsEvent:id,title,slug,event_type,status',
        'trendingMetric:id,post_id,views,comments_count,engagement_score',
        'approvedComments' => fn ($query) => $query
            ->whereNull('parent_id')
            ->with([
                'user:id,name,email,avatar,use_gravatar,is_gravatar',
                'replies' => fn ($replyQuery) => $replyQuery
                    ->approved()
                    ->with('user:id,name,email,avatar,use_gravatar,is_gravatar')
                    ->oldest(),
            ])
            ->oldest(),
    ]);

    $post->incrementViewCount();
    $post->load('trendingMetric');

    return view('news.show', [
        'post' => $post,
        'categories' => Category::query()
            ->news()
            ->active()
            ->withCount(['posts' => fn ($query) => $query->news()->published()])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(),
        'recentPosts' => Post::query()
            ->news()
            ->published()
            ->whereKeyNot($post->id)
            ->select(['id', 'title', 'slug', 'published_at', 'is_breaking'])
            ->latestPublished()
            ->limit(5)
            ->get(),
        'tags' => Tag::query()
            ->news()
            ->withCount(['posts' => fn ($query) => $query->news()->published()])
            ->orderByDesc('posts_count')
            ->orderBy('name')
            ->limit(12)
            ->get(),
    ]);
})->name('news.show');

Route::get('/{path}', function () {
    return view('welcome');
})->where('path', '^(?!(?:admin|api)(?:/|$)).*');
