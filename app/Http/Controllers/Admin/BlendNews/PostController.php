<?php

namespace App\Http\Controllers\Admin\BlendNews;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\NewsEvent;
use App\Models\NewsSource;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PostController extends Controller
{
    public function index(Request $request): View
    {
        $posts = Post::query()
            ->news()
            ->with(['author:id,name,email', 'primaryCategory:id,name', 'categories:id,name', 'newsSource:id,name'])
            ->when($request->query('search'), function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('title', 'like', "%{$search}%")
                        ->orWhere('excerpt', 'like', "%{$search}%")
                        ->orWhere('content', 'like', "%{$search}%");
                });
            })
            ->when($request->query('status'), fn ($query, string $status) => $query->where('status', $status))
            ->when($request->query('category_id'), fn ($query, string $categoryId) => $query->where('category_id', $categoryId))
            ->when($request->query('date'), fn ($query, string $date) => $query->whereDate('published_at', $date))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('admin.blendnews.index', [
            'posts' => $posts,
            'categories' => Category::query()->news()->orderBy('name')->get(['id', 'name']),
            'statusCounts' => Post::query()
                ->news()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status'),
            'statuses' => $this->statuses(),
        ]);
    }

    public function create(): View
    {
        return view('admin.blendnews.create', $this->formData(new Post([
            'content_type' => Post::TYPE_NEWS,
            'status' => Post::STATUS_DRAFT,
            'verification_status' => 'unverified',
            'importance_level' => 1,
            'published_at' => now(),
        ])));
    }

    public function store(Request $request): RedirectResponse
    {
        $post = Post::query()->create($this->validatedData($request));
        $this->syncRelations($post, $request);

        return redirect()
            ->route('admin.blendnews.edit', $post)
            ->with('status', 'BlendNews story created.');
    }

    public function edit(Post $blendnews): View
    {
        abort_unless($blendnews->isNews(), 404);

        return view('admin.blendnews.edit', $this->formData($blendnews->load(['categories', 'tags'])));
    }

    public function update(Request $request, Post $blendnews): RedirectResponse
    {
        abort_unless($blendnews->isNews(), 404);

        $blendnews->update($this->validatedData($request, $blendnews));
        $this->syncRelations($blendnews, $request);

        return redirect()
            ->route('admin.blendnews.edit', $blendnews)
            ->with('status', 'BlendNews story updated.');
    }

    public function destroy(Post $blendnews): RedirectResponse
    {
        abort_unless($blendnews->isNews(), 404);

        $blendnews->delete();

        return redirect()
            ->route('admin.blendnews.index')
            ->with('status', 'BlendNews story deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Post $post): array
    {
        return [
            'post' => $post,
            'categories' => Category::query()->news()->active()->orderBy('name')->get(),
            'tags' => Tag::query()->news()->orderBy('name')->get(),
            'sources' => NewsSource::query()->active()->orderBy('name')->get(),
            'events' => NewsEvent::query()->orderByDesc('started_at')->orderBy('title')->get(),
            'authors' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'statuses' => $this->statuses(),
            'verificationStatuses' => ['unverified', 'pending', 'verified', 'disputed'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function statuses(): array
    {
        return [
            Post::STATUS_DRAFT => 'Draft',
            Post::STATUS_REVIEW => 'Review',
            Post::STATUS_APPROVED => 'Approved',
            Post::STATUS_PUBLISHED => 'Published',
            Post::STATUS_ARCHIVED => 'Archived',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedData(Request $request, ?Post $post = null): array
    {
        $validated = $request->validate([
            'author_id' => ['nullable', 'integer', 'exists:users,id'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('group', 'news'))],
            'news_source_id' => ['nullable', 'integer', 'exists:news_sources,id'],
            'news_event_id' => ['nullable', 'integer', 'exists:news_events,id'],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', Rule::unique('posts', 'slug')->ignore($post?->id)],
            'excerpt' => ['nullable', 'string', 'max:1000'],
            'content' => ['required', 'string'],
            'status' => ['required', Rule::in(array_keys($this->statuses()))],
            'verification_status' => ['required', Rule::in(['unverified', 'pending', 'verified', 'disputed'])],
            'importance_level' => ['required', 'integer', 'min:1', 'max:5'],
            'featured_image_path' => ['nullable', 'string', 'max:2048'],
            'featured_image_alt' => ['nullable', 'string', 'max:255'],
            'seo_title' => ['nullable', 'string', 'max:255'],
            'seo_description' => ['nullable', 'string', 'max:500'],
            'published_at' => ['nullable', 'date'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['integer', Rule::exists('categories', 'id')->where(fn ($query) => $query->where('group', 'news'))],
            'tags' => ['nullable', 'array'],
            'tags.*' => ['integer', Rule::exists('tags', 'id')->where(fn ($query) => $query->where('group', 'news'))],
        ]);

        $status = $validated['status'];
        $publishedAt = $validated['published_at'] ?? null;

        if ($status === Post::STATUS_PUBLISHED && ! $publishedAt) {
            $publishedAt = now();
        }

        $featuredImagePath = trim((string) ($validated['featured_image_path'] ?? ''));
        $featuredImage = $featuredImagePath !== ''
            ? [
                'path' => Str::of($featuredImagePath)->replaceStart('/media/', '')->replaceStart('media/', '')->toString(),
                'alt' => $validated['featured_image_alt'] ?: $validated['title'],
            ]
            : null;

        return [
            'author_id' => $validated['author_id'] ?? null,
            'category_id' => $validated['category_id'] ?? Arr::first($validated['categories'] ?? []),
            'news_source_id' => $validated['news_source_id'] ?? null,
            'news_event_id' => $validated['news_event_id'] ?? null,
            'content_type' => Post::TYPE_NEWS,
            'title' => $validated['title'],
            'slug' => $validated['slug'] ?: Str::slug($validated['title']),
            'excerpt' => $validated['excerpt'] ?? null,
            'content' => $validated['content'],
            'status' => $status,
            'is_verified' => $validated['verification_status'] === 'verified',
            'verification_status' => $validated['verification_status'],
            'is_breaking' => $request->boolean('is_breaking'),
            'is_featured' => $request->boolean('is_featured'),
            'importance_level' => (int) $validated['importance_level'],
            'featured_image' => $featuredImage,
            'seo' => [
                'title' => $validated['seo_title'] ?? null,
                'description' => $validated['seo_description'] ?? null,
            ],
            'published_at' => $publishedAt,
            'reviewed_at' => $status === Post::STATUS_REVIEW ? now() : $post?->reviewed_at,
            'approved_at' => $status === Post::STATUS_APPROVED ? now() : $post?->approved_at,
            'archived_at' => $status === Post::STATUS_ARCHIVED ? now() : null,
        ];
    }

    private function syncRelations(Post $post, Request $request): void
    {
        $post->categories()->sync($request->input('categories', []));
        $post->tags()->sync($request->input('tags', []));
    }
}
