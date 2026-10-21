@props(['title' => null])
@php
    $nav = [
        ['label' => 'Dashboard', 'route' => 'dashboard', 'ready' => true],
        ['label' => 'New extraction', 'route' => 'extractions.create', 'ready' => true],
        ['label' => 'Extractions', 'route' => 'extractions.index', 'ready' => true, 'match' => ['extractions.index', 'extractions.show']],
        ['label' => 'Billing', 'route' => null, 'ready' => false],
        ['label' => 'Settings', 'route' => 'settings.profile', 'ready' => true, 'match' => 'settings.*'],
    ];
    $user = auth()->user();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <x-head :title="$title" />
</head>
<body class="min-h-screen bg-bg text-ink antialiased" x-data="{ nav: false }">
    <a href="#main" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-surface focus:px-3 focus:py-2">Skip to content</a>

    {{-- Sidebar --}}
    <aside class="fixed inset-y-0 left-0 z-40 w-[var(--sidebar-w)] -translate-x-full border-r border-line bg-surface transition-transform duration-[var(--dur-base)] lg:translate-x-0"
        x-bind:class="nav && 'translate-x-0'" aria-label="Application">
        <div class="flex h-[var(--topbar-h)] items-center px-5">
            <a href="{{ route('dashboard') }}" aria-label="StoreSiphon dashboard"><x-logo variant="horizontal" :height="24" /></a>
        </div>
        <nav class="space-y-0.5 px-3 py-4">
            @foreach ($nav as $item)
                @php $active = $item['route'] && request()->routeIs(...(array) ($item['match'] ?? $item['route'])); @endphp
                @if ($item['ready'])
                    <a href="{{ route($item['route']) }}" @if ($active) aria-current="page" @endif
                        class="relative flex h-9 items-center gap-3 rounded-md px-3 text-sm font-medium {{ $active ? 'bg-primary-subtle text-primary' : 'text-secondary hover:bg-sunken hover:text-ink' }}">
                        @if ($active)<span class="absolute left-0 top-1/2 h-4 w-0.5 -translate-y-1/2 rounded-xs bg-primary" aria-hidden="true"></span>@endif
                        {{ $item['label'] }}
                    </a>
                @else
                    <span class="flex h-9 items-center justify-between gap-3 rounded-md px-3 text-sm font-medium text-muted" aria-disabled="true">
                        {{ $item['label'] }} <span class="text-[10px] font-semibold uppercase tracking-[var(--tracking-caps)]">Soon</span>
                    </span>
                @endif
            @endforeach
            @if ($user?->isAdmin())
                <p class="px-3 pt-6 pb-2 text-xs font-semibold uppercase tracking-[var(--tracking-caps)] text-muted">Admin</p>
                <span class="flex h-9 items-center px-3 text-sm text-muted" aria-disabled="true">Administration (Phase 4)</span>
            @endif
        </nav>
    </aside>
    <div x-cloak x-show="nav" x-on:click="nav = false" class="fixed inset-0 z-30 bg-[#081620]/60 lg:hidden"></div>

    <div class="lg:pl-[var(--sidebar-w)]">
        {{-- Top bar --}}
        <header class="sticky top-0 z-20 flex h-[var(--topbar-h)] items-center justify-between gap-4 border-b border-line bg-bg/90 px-4 backdrop-blur sm:px-6">
            <div class="flex items-center gap-3">
                <button type="button" class="lg:hidden inline-flex size-9 items-center justify-center rounded-md text-secondary hover:bg-sunken" x-on:click="nav = !nav" aria-label="Toggle navigation">
                    <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true"><path d="M2 4h14M2 9h14M2 14h14" stroke="currentColor" stroke-width="1.75" stroke-linecap="square"/></svg>
                </button>
                @if ($title)<h1 class="text-base font-semibold">{{ $title }}</h1>@endif
            </div>
            <div class="flex items-center gap-3" x-data="{ open: false }">
                <button type="button" x-on:click="open = !open" x-on:keydown.escape.window="open = false" x-bind:aria-expanded="open" aria-haspopup="menu"
                    class="flex items-center gap-2 rounded-md px-1.5 py-1 hover:bg-sunken">
                    <span class="inline-flex size-8 items-center justify-center rounded-full bg-primary-subtle text-xs font-semibold text-primary">{{ $user?->initials() }}</span>
                    <span class="hidden text-sm font-medium sm:block">{{ $user?->name }}</span>
                </button>
                <div x-cloak x-show="open" x-on:click.outside="open = false" role="menu"
                    class="absolute right-4 top-[calc(var(--topbar-h)-4px)] w-56 rounded-lg border border-line bg-raised p-1 shadow-md">
                    <p class="truncate px-3 py-2 text-xs text-muted">{{ $user?->email }}</p>
                    <a role="menuitem" href="{{ route('settings.profile') }}" class="block rounded-md px-3 py-2 text-sm hover:bg-sunken">Settings</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button role="menuitem" type="submit" class="block w-full rounded-md px-3 py-2 text-left text-sm hover:bg-sunken">Sign out</button>
                    </form>
                </div>
            </div>
        </header>

        <main id="main" class="mx-auto max-w-[var(--container-max)] px-4 py-6 sm:px-6 sm:py-8">
            @if (session('status'))
                <x-alert tone="success" class="mb-6">{{ __(session('status')) }}</x-alert>
            @endif
            {{ $slot }}
        </main>
    </div>
</body>
</html>
