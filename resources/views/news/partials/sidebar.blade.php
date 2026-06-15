<div class="space-y-5 lg:sticky lg:top-28">
    <section class="border border-border bg-card p-5">
        <p class="font-heading text-xs tracking-[0.24em] text-primary">NEWS CATEGORIES</p>
        <div class="mt-5 space-y-2">
            @forelse($categories as $category)
                <a href="{{ route('news.categories.show', $category->slug) }}" class="flex items-center justify-between border border-border bg-background px-4 py-3 text-sm transition hover:border-primary hover:text-white">
                    <span>{{ $category->name }}</span>
                    <span class="font-heading text-accent">{{ $category->posts_count ?? 0 }}</span>
                </a>
            @empty
                <p class="text-sm text-muted-foreground">No categories yet.</p>
            @endforelse
        </div>
    </section>

    <section class="border border-border bg-card p-5">
        <p class="font-heading text-xs tracking-[0.24em] text-primary">RECENT STORIES</p>
        <div class="mt-5 space-y-3">
            @forelse($recentPosts as $recentPost)
                <a href="{{ route('news.show', $recentPost->slug) }}" class="block border border-border bg-background p-4 transition hover:border-primary">
                    <p class="font-heading text-lg leading-tight text-white">{{ $recentPost->title }}</p>
                    <div class="mt-2 flex items-center justify-between text-xs text-muted-foreground">
                        <span>{{ $recentPost->published_at?->format('M j') }}</span>
                        @if($recentPost->is_breaking)
                            <span class="text-primary">Breaking</span>
                        @endif
                    </div>
                </a>
            @empty
                <p class="text-sm text-muted-foreground">No recent stories yet.</p>
            @endforelse
        </div>
    </section>

    <section class="border border-border bg-card p-5">
        <p class="font-heading text-xs tracking-[0.24em] text-primary">TOPICS</p>
        <div class="mt-5 flex flex-wrap gap-2">
            @forelse($tags as $tag)
                <a href="{{ route('news.index', ['tag' => $tag->slug]) }}" class="border border-border bg-background px-3 py-2 font-heading text-xs tracking-[0.12em] text-muted-foreground transition hover:border-accent hover:text-accent">
                    {{ $tag->name }}
                </a>
            @empty
                <p class="text-sm text-muted-foreground">No topics yet.</p>
            @endforelse
        </div>
    </section>
</div>
