@php
    $avatarUrl = $comment->user?->getAvatarUrl();
    $initials = collect(explode(' ', $comment->author_name ?: $comment->user?->name ?: 'Guest'))
        ->filter()
        ->map(fn ($part) => mb_substr($part, 0, 1))
        ->take(2)
        ->implode('');
@endphp

<div class="border border-border bg-background p-4">
    <div class="flex gap-4">
        <div class="h-11 w-11 shrink-0 overflow-hidden rounded-full border border-border bg-primary">
            @if($avatarUrl)
                <img src="{{ $avatarUrl }}" alt="{{ $comment->author_name }}" class="h-full w-full object-cover">
            @else
                <div class="flex h-full w-full items-center justify-center font-heading text-lg text-white">{{ $initials ?: 'G' }}</div>
            @endif
        </div>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <p class="font-semibold text-white">{{ $comment->author_name }}</p>
                <span class="text-xs text-muted-foreground">{{ $comment->created_at?->diffForHumans() }}</span>
            </div>
            <p class="mt-2 whitespace-pre-wrap text-sm leading-6 text-muted-foreground">{{ $comment->content }}</p>

            @if($comment->replies->count())
                <div class="mt-4 space-y-3 border-l border-border pl-4">
                    @foreach($comment->replies as $reply)
                        @include('news.partials.comment', ['comment' => $reply])
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</div>
