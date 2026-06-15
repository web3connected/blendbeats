@extends('news.layouts.app')

@section('title', 'News | The Blend Battlegrounds')
@section('description', 'Latest Blend Battlegrounds news, platform updates, DJ milestones, event coverage, and community stories.')

@section('content')
        <section class="border-b border-border bg-[linear-gradient(135deg,rgba(255,31,31,0.16),rgba(255,191,0,0.08)_38%,rgba(0,0,0,0)_72%)]">
            <div class="mx-auto max-w-[1520px] px-4 py-16 sm:px-6 lg:px-12 xl:px-16">
                <div class="grid gap-8 lg:grid-cols-[minmax(0,1fr)_420px] lg:items-end">
                    <div>
                        <p class="mb-5 font-heading text-sm tracking-[0.28em] text-primary">BLEND NEWSROOM</p>
                        <h1 class="max-w-4xl font-heading text-5xl leading-none text-white sm:text-6xl lg:text-7xl">
                            News, milestones, and culture from the battleground.
                        </h1>
                        <p class="mt-6 max-w-3xl text-lg leading-8 text-muted-foreground">
                            Follow platform updates, DJ stories, event coverage, featured moments, and the signals shaping the Blend community.
                        </p>
                    </div>

                    <form method="GET" action="{{ route('news.index') }}" class="border border-border bg-card p-5">
                        <label for="news-search" class="block font-heading text-sm tracking-[0.18em] text-accent">SEARCH STORIES</label>
                        <div class="mt-4 flex gap-3">
                            <input
                                id="news-search"
                                type="search"
                                name="search"
                                value="{{ request('search') }}"
                                placeholder="Search news"
                                class="min-w-0 flex-1 border border-input bg-background px-4 py-3 text-sm text-white outline-none focus:border-primary"
                            >
                            <button type="submit" class="bg-primary px-5 py-3 font-heading text-sm tracking-[0.12em] text-primary-foreground">
                                Search
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-[1520px] px-4 py-10 sm:px-6 lg:px-12 xl:px-16">
            <div class="grid grid-cols-1 gap-8 lg:grid-cols-4">
                <div class="lg:col-span-3">
                    @include('news.partials.list', [
                        'posts' => $posts,
                        'categories' => $categories ?? collect(),
                        'title' => 'Latest News',
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
@endsection
