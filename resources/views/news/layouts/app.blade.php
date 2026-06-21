<!doctype html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'BlendNews | The Blend Battlegrounds')</title>
    @hasSection('description')
        <meta name="description" content="@yield('description')">
    @endif
    @stack('head')

    @vite(['resources/js/React/Frontend/styles/app.css'])
</head>
<body>
    <div class="min-h-screen bg-background text-foreground">
        <header class="sticky top-0 z-50 border-b border-[#2a2a2a] bg-[#0a0a0a]/95 backdrop-blur-sm">
            <div class="container mx-auto px-4">
                <div class="flex h-20 items-center justify-between gap-6">
                    <a href="{{ route('home') }}" class="flex max-w-[390px] shrink-0 items-center overflow-hidden">
                        <img
                            src="{{ Vite::asset('resources/assets/logo.png') }}"
                            alt="The Blend Battlegrounds"
                            class="h-20 w-auto max-w-full shrink-0 object-contain"
                        >
                    </a>

                    <nav class="hidden items-center gap-8 md:flex">
                        @foreach([
                            ['href' => '/battles', 'label' => 'BATTLES'],
                            ['href' => '/mixes', 'label' => 'MIXES'],
                            ['href' => '/pricing', 'label' => 'PRICING'],
                            ['href' => '/merch', 'label' => 'MERCH'],
                        ] as $item)
                            <a
                                href="{{ $item['href'] }}"
                                class="font-heading text-base font-bold tracking-widest text-[#aaaaaa] transition-colors hover:text-primary"
                            >
                                {{ $item['label'] }}
                            </a>
                        @endforeach

                        <a href="{{ route('news.index') }}" class="border-b-2 border-primary pb-0.5 font-heading text-base font-bold tracking-widest">
                            <span class="text-primary">BLEND</span><span class="text-accent">NEWS</span>
                        </a>

                        <a href="/djs" class="font-heading text-base font-bold tracking-widest text-[#aaaaaa] transition-colors hover:text-primary">
                            DJ
                        </a>
                    </nav>

                    <div class="flex shrink-0 items-center gap-3">
                        <a
                            href="/battles"
                            class="hidden bg-primary px-5 py-2 font-heading text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90 md:inline-flex"
                        >
                            Enter Battle
                        </a>
                        <a
                            href="/dj/portfolio?upload=1"
                            class="hidden h-10 w-10 items-center justify-center border border-[#333333] bg-[#111111] text-[#dddddd] transition-colors hover:border-primary hover:text-primary md:inline-flex"
                            aria-label="Upload a mix"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 3v12"></path>
                                <path d="m17 8-5-5-5 5"></path>
                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                            </svg>
                        </a>
                        <a
                            href="/merch"
                            class="hidden h-10 w-10 items-center justify-center border border-[#333333] bg-[#111111] text-[#dddddd] transition-colors hover:border-primary hover:text-primary md:inline-flex"
                            aria-label="Open cart"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="8" cy="21" r="1"></circle>
                                <circle cx="19" cy="21" r="1"></circle>
                                <path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57L22 7H5.12"></path>
                            </svg>
                        </a>
                        <a
                            href="/account/notifications"
                            class="hidden h-10 w-10 items-center justify-center border border-[#333333] bg-[#111111] text-[#dddddd] transition-colors hover:border-primary hover:text-primary md:inline-flex"
                            aria-label="Notifications"
                        >
                            <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M10.27 21a2 2 0 0 0 3.46 0"></path>
                                <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 7h18s-3 0-3-7"></path>
                            </svg>
                        </a>
                        @auth
                            <a
                                href="/account"
                                class="hidden h-10 items-center gap-2 border border-[#333333] bg-[#111111] px-3 font-heading text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary md:inline-flex"
                            >
                                <span class="flex h-6 w-6 items-center justify-center bg-primary text-[10px] font-black text-white">
                                    {{ strtoupper(substr(auth()->user()->name ?? 'U', 0, 1)) }}
                                </span>
                                <span class="max-w-24 truncate">{{ auth()->user()->name }}</span>
                            </a>
                        @else
                            <a
                                href="/login"
                                class="hidden h-10 items-center gap-2 border border-[#444444] px-4 font-heading text-xs font-bold uppercase tracking-widest text-[#dddddd] transition-colors hover:border-primary hover:text-primary md:inline-flex"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"></path>
                                    <path d="m10 17 5-5-5-5"></path>
                                    <path d="M15 12H3"></path>
                                </svg>
                                Login
                            </a>
                            <a
                                href="/register"
                                class="hidden h-10 items-center gap-2 bg-primary px-4 font-heading text-xs font-bold uppercase tracking-widest text-white transition-colors hover:bg-primary/90 lg:inline-flex"
                            >
                                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"></path>
                                    <circle cx="9" cy="7" r="4"></circle>
                                    <path d="M19 8v6"></path>
                                    <path d="M22 11h-6"></path>
                                </svg>
                                Register
                            </a>
                        @endauth
                    </div>
                </div>
            </div>
        </header>

        <main>
            @yield('content')
        </main>

        <footer class="mt-auto border-t-2 border-primary bg-[#0a0a0a]">
            <div class="h-px bg-gradient-to-r from-transparent via-[#FFB800] to-transparent"></div>
            <div class="container mx-auto px-4 py-12">
                <div class="grid grid-cols-1 gap-10 md:grid-cols-4">
                    <div>
                        <a href="{{ route('home') }}" class="mb-4 inline-flex max-w-[390px] items-center overflow-hidden">
                            <img
                                src="{{ Vite::asset('resources/assets/logo.png') }}"
                                alt="The Blend Battlegrounds"
                                class="h-24 w-auto max-w-full shrink-0 object-contain"
                            >
                        </a>
                        <p class="mb-4 text-sm leading-relaxed text-[#888888]">
                            The premier underground DJ battle platform. Where the culture lives, the craft is tested, and legends are made.
                        </p>
                    </div>

                    <div>
                        <h4 class="mb-4 border-b border-[#2a2a2a] pb-2 font-heading text-xs font-bold uppercase tracking-widest text-white">
                            Platform
                        </h4>
                        <ul class="space-y-2">
                            @foreach([
                                ['href' => '/battles', 'label' => 'DJ Battles'],
                                ['href' => '/mixes', 'label' => 'Mix Submissions'],
                                ['href' => '/pricing', 'label' => 'Pricing'],
                                ['href' => '/djs', 'label' => 'DJ Profiles'],
                                ['href' => route('news.index'), 'label' => 'BlendNews'],
                            ] as $item)
                                <li><a href="{{ $item['href'] }}" class="text-sm text-[#888888] transition-colors hover:text-primary">{{ $item['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>

                    <div>
                        <h4 class="mb-4 border-b border-[#2a2a2a] pb-2 font-heading text-xs font-bold uppercase tracking-widest text-white">
                            Shop
                        </h4>
                        <ul class="space-y-2">
                            @foreach(['Merchandise', 'Print-On-Demand', 'Affiliate Picks', 'Vendor Products'] as $label)
                                <li><a href="/merch" class="text-sm text-[#888888] transition-colors hover:text-primary">{{ $label }}</a></li>
                            @endforeach
                        </ul>
                    </div>

                    <div>
                        <h4 class="mb-4 border-b border-[#2a2a2a] pb-2 font-heading text-xs font-bold uppercase tracking-widest text-white">
                            Support
                        </h4>
                        <ul class="space-y-2">
                            @foreach([
                                ['href' => '/about', 'label' => 'About Us'],
                                ['href' => '/contact', 'label' => 'Contact'],
                                ['href' => '/privacy', 'label' => 'Privacy Policy'],
                                ['href' => '/terms', 'label' => 'Terms of Service'],
                            ] as $item)
                                <li><a href="{{ $item['href'] }}" class="text-sm text-[#888888] transition-colors hover:text-primary">{{ $item['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <div class="mt-10 flex flex-col items-center justify-between gap-4 border-t border-[#1a1a1a] pt-6 md:flex-row">
                    <p class="font-heading text-xs uppercase tracking-widest text-[#555555]">The Culture. The Craft. The Battle.</p>
                    <p class="text-xs text-[#444444]">&copy; {{ now()->year }} The Blend Battlegrounds USA. All rights reserved.</p>
                </div>
            </div>
        </footer>
    </div>
</body>
</html>
