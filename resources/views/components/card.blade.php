@props(['title' => null, 'padding' => 'p-6'])
<section {{ $attributes->merge(['class' => 'rounded-lg border border-line bg-surface']) }}>
    @if ($title || isset($actions))
        <header class="flex items-center justify-between gap-4 border-b border-line px-6 py-4">
            <h2 class="text-xl font-semibold text-ink">{{ $title }}</h2>
            {{ $actions ?? '' }}
        </header>
    @endif
    <div class="{{ $padding }}">{{ $slot }}</div>
</section>
