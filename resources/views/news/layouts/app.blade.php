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
                            ^
                        </a>
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
