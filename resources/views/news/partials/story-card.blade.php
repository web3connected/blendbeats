@php
    $image = data_get($post->featured_image, 'url') ?? data_get($post->featured_image, 'path');
    $imageUrl = $image ? (str_starts_with($image, 'http') ? $image : asset($image)) : null;
    $category = $post->primaryCategory ?? $post->categories->first();
    $views = $post->trendingMetric?->views ?? 0;
@endphp

<article class="group flex h-full flex-col overflow-hidden border border-border bg-card transition hover:border-primary/70">
    <div class="relative aspect-[16/10] overflow-hidden border-b border-border bg-[radial-gradient(circle_at_center,rgba(255,191,0,0.22),rgba(20,20,20,0.76)_44%,rgba(0,0,0,0.95)_100%)]">
        @if($imageUrl)
            <img src="{{ $imageUrl }}" alt="{{ $post->title }}" class="h-full w-full object-cover opacity-80 transition duration-300 group-hover:scale-105 group-hover:opacity-95">
            <div class="absolute inset-0 bg-gradient-to-t from-black via-black/35 to-transparent"></div>
        @else
            <div class="flex h-full items-center justify-center">
                <div class="flex h-20 w-20 items-center justify-center rounded-full border-4 border-accent/80 text-accent">
                    <span class="font-heading text-3xl">BB</span>
                </div>
            </div>
        @endif

        <div class="absolute left-4 top-4 flex gap-2">
            @if($post->is_breaking)
                <span class="bg-primary px-3 py-1 font-heading text-xs tracking-[0.14em] text-white">Breaking</span>
            @endif
            @if($post->is_featured)
                <span class="bg-accent px-3 py-1 font-heading text-xs tracking-[0.14em] text-black">Featured</span>
            @endif
        </div>
    </div>

    <div class="flex flex-1 flex-col p-5">
        <div class="mb-4 flex items-center justify-between gap-3 text-xs">
            <span class="font-heading tracking-[0.16em] text-accent">{{ $category?->name ?? 'News' }}</span>
            <span class="text-muted-foreground">{{ $post->published_at?->format('M j, Y') }}</span>
        </div>

        <h3 class="font-heading text-3xl leading-none text-white">
            {{ $post->title }}
        </h3>

        <p class="mt-4 line-clamp-3 flex-1 text-sm leading-6 text-muted-foreground">
            {{ $post->excerpt ?: str($post->content)->stripTags()->limit(150) }}
        </p>

        <div class="mt-5 border-t border-border pt-4">
            <div class="flex items-center justify-between text-sm text-muted-foreground">
                <span>{{ $post->newsSource?->name ?? $post->author?->name ?? 'Blend Newsroom' }}</span>
                <span>{{ number_format($views) }} views</span>
            </div>
            <a href="{{ route('news.show', $post->slug) }}" class="mt-4 inline-flex w-full items-center justify-center bg-primary px-4 py-3 font-heading text-sm tracking-[0.12em] text-white transition hover:bg-primary/85">
                Read Story
            </a>
        </div>
    </div>
</article>
