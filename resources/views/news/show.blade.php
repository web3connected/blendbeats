@php
    $featuredImage = data_get($post->featured_image, 'url') ?? data_get($post->featured_image, 'path');
    $featuredImageUrl = $featuredImage ? (str_starts_with($featuredImage, 'http') ? $featuredImage : asset($featuredImage)) : null;
    $description = $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->content), 160);
    $category = $post->primaryCategory ?? $post->categories->first();
    $views = $post->trendingMetric?->views ?? 0;
@endphp

@extends('news.layouts.app')

@section('title', $post->title.' | The Blend Battlegrounds')
@section('description', $description)

@push('head')
    <meta property="og:title" content="{{ $post->title }}">
    <meta property="og:description" content="{{ $description }}">
    @if($featuredImageUrl)
        <meta property="og:image" content="{{ $featuredImageUrl }}">
    @endif
    <meta property="og:type" content="article">
    <meta property="article:author" content="{{ $post->author?->name ?? 'Blend Newsroom' }}">
    <meta property="article:published_time" content="{{ $post->published_at?->toISOString() ?? $post->created_at?->toISOString() }}">
    <meta property="article:section" content="{{ $category?->name ?? 'News' }}">
@endpush

@section('content')
        <section class="border-b border-border bg-[linear-gradient(135deg,rgba(255,31,31,0.14),rgba(255,191,0,0.08)_34%,rgba(0,0,0,0)_76%)]">
            <div class="mx-auto max-w-[1520px] px-4 py-10 sm:px-6 lg:px-12 xl:px-16">
                <nav class="mb-8 text-sm text-muted-foreground">
                    <ol class="flex flex-wrap items-center gap-2">
                        <li><a href="{{ route('home') }}" class="transition hover:text-primary">Home</a></li>
                        <li class="text-border">/</li>
                        <li><a href="{{ route('news.index') }}" class="transition hover:text-primary">News</a></li>
                        <li class="text-border">/</li>
                        <li class="max-w-xl truncate text-white">{{ $post->title }}</li>
                    </ol>
                </nav>

                <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_420px] lg:items-end">
                    <div>
                        <div class="mb-5 flex flex-wrap gap-2">
                            @if($post->is_breaking)
                                <span class="bg-primary px-3 py-1 font-heading text-xs tracking-[0.16em] text-white">Breaking</span>
                            @endif
                            @if($post->is_featured)
                                <span class="bg-accent px-3 py-1 font-heading text-xs tracking-[0.16em] text-black">Featured</span>
                            @endif
                            <span class="border border-border bg-card px-3 py-1 font-heading text-xs tracking-[0.16em] text-accent">{{ $category?->name ?? 'News' }}</span>
                        </div>

                        <h1 class="max-w-5xl font-heading text-5xl leading-none text-white sm:text-6xl lg:text-7xl">
                            {{ $post->title }}
                        </h1>

                        @if($post->excerpt)
                            <p class="mt-6 max-w-4xl text-lg leading-8 text-muted-foreground">{{ $post->excerpt }}</p>
                        @endif
                    </div>

                    <div class="grid grid-cols-2 gap-3 border border-border bg-card p-4">
                        <div class="border border-border bg-background p-4">
                            <p class="font-heading text-xs tracking-[0.18em] text-muted-foreground">Published</p>
                            <p class="mt-2 font-heading text-2xl text-white">{{ $post->published_at?->format('M j, Y') }}</p>
                        </div>
                        <div class="border border-border bg-background p-4">
                            <p class="font-heading text-xs tracking-[0.18em] text-muted-foreground">Views</p>
                            <p class="mt-2 font-heading text-2xl text-accent">{{ number_format($views) }}</p>
                        </div>
                        <div class="border border-border bg-background p-4">
                            <p class="font-heading text-xs tracking-[0.18em] text-muted-foreground">Source</p>
                            <p class="mt-2 truncate font-heading text-2xl text-white">{{ $post->newsSource?->name ?? 'Internal' }}</p>
                        </div>
                        <div class="border border-border bg-background p-4">
                            <p class="font-heading text-xs tracking-[0.18em] text-muted-foreground">Status</p>
                            <p class="mt-2 font-heading text-2xl text-white">{{ ucfirst(str_replace('_', ' ', $post->verification_status ?? 'unverified')) }}</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-[1520px] px-4 py-10 sm:px-6 lg:px-12 xl:px-16">
            <div class="grid grid-cols-1 gap-8 lg:grid-cols-4">
                <article class="lg:col-span-3">
                    @if($featuredImageUrl)
                        <div class="mb-8 overflow-hidden border border-border bg-card">
                            <img src="{{ $featuredImageUrl }}" alt="{{ $post->title }}" class="max-h-[560px] w-full object-cover opacity-90">
                        </div>
                    @endif

                    <div class="border border-border bg-card p-6 sm:p-8 lg:p-10">
                        <div class="mb-8 flex flex-wrap items-center gap-3 border-b border-border pb-6 text-sm text-muted-foreground">
                            <span>By {{ $post->author?->name ?? 'Blend Newsroom' }}</span>
                            <span class="text-border">/</span>
                            <span>{{ $post->newsSource?->source_type ? ucfirst(str_replace('_', ' ', $post->newsSource->source_type)) : 'Platform story' }}</span>
                            @if($post->is_verified)
                                <span class="border border-accent px-3 py-1 font-heading text-xs tracking-[0.14em] text-accent">Verified</span>
                            @endif
                        </div>

                        <div class="prose prose-invert max-w-none prose-headings:font-heading prose-headings:text-white prose-a:text-accent prose-strong:text-white prose-p:text-muted-foreground prose-li:text-muted-foreground">
                            {!! str_contains($post->content, '<')
                                ? strip_tags($post->content, '<p><br><strong><b><em><i><u><a><ul><ol><li><blockquote><h2><h3><h4><pre><code>')
                                : nl2br(e($post->content)) !!}
                        </div>

                        @if($post->tags->count())
                            <div class="mt-10 border-t border-border pt-6">
                                <p class="mb-3 font-heading text-xs tracking-[0.22em] text-primary">TOPICS</p>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($post->tags as $tag)
                                        <a href="{{ route('news.index', ['tag' => $tag->slug]) }}" class="border border-border bg-background px-3 py-2 font-heading text-xs tracking-[0.12em] text-muted-foreground transition hover:border-accent hover:text-accent">
                                            {{ $tag->name }}
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <section class="mt-8 border border-border bg-card p-6 sm:p-8">
                        <div class="mb-6 flex items-center justify-between gap-4 border-b border-border pb-5">
                            <div>
                                <p class="font-heading text-xs tracking-[0.22em] text-primary">COMMUNITY NOTES</p>
                                <h2 class="mt-2 font-heading text-3xl text-white">Comments</h2>
                            </div>
                            <span class="font-heading text-2xl text-accent">{{ $post->approvedComments->count() }}</span>
                        </div>

                        <div class="space-y-4">
                            @forelse($post->approvedComments as $comment)
                                @include('news.partials.comment', ['comment' => $comment])
                            @empty
                                <p class="text-muted-foreground">No approved comments yet.</p>
                            @endforelse
                        </div>
                    </section>
                </article>

                <aside class="lg:col-span-1">
                    @include('news.partials.sidebar', [
                        'categories' => $categories ?? collect(),
                        'recentPosts' => $recentPosts ?? collect(),
                        'tags' => $tags ?? collect(),
                    ])
                </aside>
            </div>
        </section>
@endsection
