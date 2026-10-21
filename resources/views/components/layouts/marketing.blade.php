@props(['title' => null])
@php
    // Play the logo intro once per session; later pages show the static logo.
    $intro = ! session()->has('ss_logo_intro');
    session()->put('ss_logo_intro', true);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-head :title="$title" />
</head>
<body class="min-h-screen bg-bg text-ink antialiased flex flex-col">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-surface focus:px-3 focus:py-2">Skip to content</a>

    <header class="sticky top-0 z-40 border-b border-line bg-bg/90 backdrop-blur">
        <div class="mx-auto flex h-16 max-w-[var(--container-max)] items-center justify-between gap-6 px-4 sm:px-6">
            <a href="{{ route('home') }}" class="shrink-0" aria-label="StoreSiphon home">
                <x-logo variant="horizontal" :animated="$intro" :height="28" />
            </a>
            <nav class="hidden md:flex items-center gap-8 text-sm font-medium text-secondary" aria-label="Main">
                <a href="{{ route('home') }}#how-it-works" class="hover:text-ink">How it works</a>
                <a href="{{ route('home') }}#formats" class="hover:text-ink">Formats</a>
                <a href="{{ route('home') }}#pricing" class="hover:text-ink">Pricing</a>
                <a href="{{ route('try') }}" class="hover:text-ink">Try it</a>
            </nav>
            <div class="flex items-center gap-2">
                @auth
                    <x-button :href="route('dashboard')" size="sm">Dashboard</x-button>
                @else
                    <x-button :href="route('login')" variant="ghost" size="sm">Sign in</x-button>
                    <x-button :href="route('register')" size="sm">Start free</x-button>
                @endauth
            </div>
        </div>
    </header>

    <main id="main" class="flex-1">
        @if (session('status'))
            <div class="mx-auto max-w-[var(--container-max)] px-4 pt-6 sm:px-6"><x-alert tone="success">{{ session('status') }}</x-alert></div>
        @endif
        {{ $slot }}
    </main>

    <footer class="border-t border-line bg-surface">
        <div class="mx-auto grid max-w-[var(--container-max)] gap-8 px-4 py-12 sm:px-6 md:grid-cols-4">
            <div class="md:col-span-2 space-y-3">
                <x-logo variant="horizontal" :height="24" />
                <p class="max-w-sm text-sm text-muted">Clean, structured product data from public WooCommerce stores. We respect robots.txt, rate limits and store owners' opt-outs.</p>
            </div>
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-[var(--tracking-caps)] text-muted">Product</h3>
                <ul class="mt-3 space-y-2 text-sm text-secondary">
                    <li><a class="hover:text-ink" href="{{ route('home') }}#how-it-works">How it works</a></li>
                    <li><a class="hover:text-ink" href="{{ route('home') }}#pricing">Pricing</a></li>
                </ul>
            </div>
            <div>
                <h3 class="text-xs font-semibold uppercase tracking-[var(--tracking-caps)] text-muted">Legal</h3>
                <ul class="mt-3 space-y-2 text-sm text-secondary">
                    <li><a class="hover:text-ink" href="{{ route('legal', 'terms') }}">Terms of service</a></li>
                    <li><a class="hover:text-ink" href="{{ route('legal', 'privacy') }}">Privacy policy</a></li>
                    <li><a class="hover:text-ink" href="{{ route('legal', 'refunds') }}">Refund policy</a></li>
                    <li><a class="hover:text-ink" href="{{ route('store-owners') }}">For store owners</a></li>
                </ul>
            </div>
        </div>
        <div class="border-t border-line">
            <div class="mx-auto flex max-w-[var(--container-max)] flex-wrap items-center justify-between gap-4 px-4 py-4 text-xs text-muted sm:px-6">
                <span>© {{ date('Y') }} StoreSiphon. Not affiliated with WooCommerce or WordPress.</span>
                <x-theme-switcher />
            </div>
        </div>
    </footer>
</body>
</html>
