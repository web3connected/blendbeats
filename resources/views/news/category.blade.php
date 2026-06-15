<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ $category->name }} News | The Blend Battlegrounds</title>
    <meta
        name="description"
        content="{{ $category->description ?: 'Browse '.$category->name.' news, stories, updates, and milestones from The Blend Battlegrounds.' }}"
    >

    @vite(['resources/js/React/Frontend/styles/app.css'])
</head>
<body>
    <main class="min-h-screen bg-background text-foreground">
        <section class="mx-auto max-w-[1520px] px-4 py-10 sm:px-6 lg:px-12 xl:px-16">
            <div class="grid grid-cols-1 gap-8 lg:grid-cols-4">
                <div class="lg:col-span-3">
                    <nav class="mb-6 text-sm text-muted-foreground">
                        <ol class="flex flex-wrap items-center gap-2">
                            <li><a href="{{ route('home') }}" class="transition hover:text-primary">Home</a></li>
                            <li class="text-border">/</li>
                            <li><a href="{{ route('news.index') }}" class="transition hover:text-primary">News</a></li>
                            <li class="text-border">/</li>
                            <li class="font-medium text-white">{{ $category->name }}</li>
                        </ol>
                    </nav>

                    <header class="mb-8 border border-border bg-card p-6 sm:p-8">
                        <div class="flex items-start gap-4">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center bg-primary text-white">
                                <span class="font-heading text-2xl">#</span>
                            </div>
                            <div class="min-w-0">
                                <p class="font-heading text-xs tracking-[0.24em] text-accent">NEWS CATEGORY</p>
                                <h1 class="mt-2 font-heading text-5xl leading-none text-white">{{ $category->name }}</h1>

                                @if($category->description)
                                    <p class="mt-4 max-w-3xl text-muted-foreground">{{ $category->description }}</p>
                                @endif

                                <p class="mt-5 text-sm text-muted-foreground">
                                    {{ $posts->total() }} {{ \Illuminate\Support\Str::plural('story', $posts->total()) }} in this category
                                </p>
                            </div>
                        </div>
                    </header>

                    @include('news.partials.list', [
                        'posts' => $posts,
                        'categories' => $categories ?? collect(),
                        'title' => null,
                    ])
                </div>

                <aside class="lg:col-span-1">
                    @include('news.partials.sidebar', [
                        'categories' => $categories ?? collect(),
                        'recentPosts' => $recentPosts ?? collect(),
                        'tags' => $tags ?? collect(),
                    ])
                </aside>
            </div>
        </section>
    </main>
</body>
</html>
