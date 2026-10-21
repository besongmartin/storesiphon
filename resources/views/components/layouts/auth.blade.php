@props(['title' => null, 'heading' => null, 'subheading' => null])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-head :title="$title ?? $heading" />
</head>
<body class="min-h-screen bg-bg text-ink antialiased">
    <main id="main" class="flex min-h-screen flex-col items-center justify-center px-4 py-12">
        <a href="{{ route('home') }}" class="mb-8" aria-label="StoreSiphon home">
            <x-logo variant="symbol" :height="56" />
        </a>
        <div class="w-full max-w-sm">
            @if ($heading)
                <div class="mb-6 text-center">
                    <h1 class="text-2xl font-semibold tracking-[var(--tracking-tight)]">{{ $heading }}</h1>
                    @if ($subheading)
                        <p class="mt-2 text-sm text-muted">{{ $subheading }}</p>
                    @endif
                </div>
            @endif
            @if (session('status'))
                <x-alert tone="success" class="mb-4">{{ session('status') }}</x-alert>
            @endif
            <div class="rounded-xl border border-line bg-surface p-6 shadow-sm sm:p-8">
                {{ $slot }}
            </div>
            @isset($footer)
                <div class="mt-6 text-center text-sm text-muted">{{ $footer }}</div>
            @endisset
        </div>
    </main>
</body>
</html>
