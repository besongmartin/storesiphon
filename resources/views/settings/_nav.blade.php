<nav class="flex gap-1 border-b border-line" aria-label="Settings">
    @foreach (['settings.profile' => 'Profile', 'settings.security' => 'Security'] as $route => $label)
        <a href="{{ route($route) }}" @if (request()->routeIs($route)) aria-current="page" @endif
            class="-mb-px border-b-2 px-3 py-2.5 text-sm font-medium {{ request()->routeIs($route) ? 'border-primary text-primary' : 'border-transparent text-secondary hover:text-ink' }}">{{ $label }}</a>
    @endforeach
</nav>
