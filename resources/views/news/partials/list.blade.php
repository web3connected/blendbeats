<div>
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4 border-b border-border pb-5">
        <div>
            <p class="font-heading text-xs tracking-[0.26em] text-accent">PUBLIC NEWS FEED</p>
            <h2 class="mt-2 font-heading text-4xl leading-none text-white">{{ $title ?? 'Latest News' }}</h2>
        </div>
        <p class="text-sm text-muted-foreground">{{ $posts->total() ?? $posts->count() }} stories</p>
    </div>

    @if($posts->count())
        <div class="grid gap-5 md:grid-cols-2 xl:grid-cols-3">
            @foreach($posts as $post)
                @include('news.partials.story-card', ['post' => $post])
            @endforeach
        </div>

        <div class="mt-8">
            {{ $posts->links() }}
        </div>
    @else
        <div class="border border-border bg-card p-10 text-center">
            <p class="font-heading text-3xl text-white">No news stories are published yet.</p>
            <p class="mt-3 text-muted-foreground">Published newsroom stories will appear here.</p>
        </div>
    @endif
</div>
