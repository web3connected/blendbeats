<?php

namespace App\Http\Controllers\Admin\BlendNews;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\NewsEvent;
use App\Models\NewsSource;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use App\Services\BlendNewsImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class PostController extends Controller
{
    public function __construct(private readonly BlendNewsImageService $images) {}

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
        $data = $this->validatedData($request);
        $storedPath = null;

        try {
            if ($request->hasFile('featured_image')) {
                $storedPath = $this->images->store($request->file('featured_image'));
                $data['featured_image'] = $this->featuredImageData($storedPath, $request, $data['title']);
            }

            $post = DB::transaction(function () use ($data, $request): Post {
                $post = Post::query()->create($data);
                $this->syncRelations($post, $request);

                return $post;
            });
        } catch (Throwable $exception) {
            $this->images->deleteManaged($storedPath);
            Log::error('Blend News story creation failed.', ['exception' => $exception::class]);

            return back()->withInput()->withErrors([
                'featured_image' => 'The story could not be saved. Please verify the image and try again.',
            ]);
        }

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

        $data = $this->validatedData($request, $blendnews);
        $oldPath = data_get($blendnews->featured_image, 'path');
        $storedPath = null;

        try {
            if ($request->hasFile('featured_image')) {
                $storedPath = $this->images->store($request->file('featured_image'));
                $data['featured_image'] = $this->featuredImageData($storedPath, $request, $data['title']);
            } else {
                $data['featured_image'] = $this->featuredImageData(
                    $oldPath,
                    $request,
                    $data['title'],
                    data_get($blendnews->featured_image, 'url'),
                );
            }

            DB::transaction(function () use ($blendnews, $data, $request): void {
                $blendnews->update($data);
                $this->syncRelations($blendnews, $request);
            });
        } catch (Throwable $exception) {
            $this->images->deleteManaged($storedPath);
            Log::error('Blend News story update failed.', [
                'post_id' => $blendnews->id,
                'exception' => $exception::class,
            ]);

            return back()->withInput()->withErrors([
                'featured_image' => 'The story could not be updated. The existing image was kept.',
            ]);
        }

        if ($storedPath !== null) {
            $this->images->deleteManaged($oldPath);
        }

        return redirect()
            ->route('admin.blendnews.edit', $blendnews)
            ->with('status', 'BlendNews story updated.');
    }

    public function destroy(Post $blendnews): RedirectResponse
    {
        abort_unless($blendnews->isNews(), 404);

        $path = data_get($blendnews->featured_image, 'path');
        $blendnews->delete();
        $this->images->deleteManaged($path);

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
            'featured_image' => ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.(int) config('blendnews.images.max_kilobytes', 5120)],
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
            'featured_image' => null,
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

    /**
     * @return array{path?: string, url?: string, alt: string}|null
     */
    private function featuredImageData(?string $path, Request $request, string $title, ?string $url = null): ?array
    {
        if (! filled($path) && ! filled($url)) {
            return null;
        }

        return array_filter([
            'path' => $path,
            'url' => $url,
            'alt' => $request->input('featured_image_alt') ?: $title,
        ], fn ($value) => $value !== null && $value !== '');
    }

    private function syncRelations(Post $post, Request $request): void
    {
        $post->categories()->sync($request->input('categories', []));
        $post->tags()->sync($request->input('tags', []));
    }
}
