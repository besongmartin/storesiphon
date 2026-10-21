@props([
    'variant' => 'primary', // primary | secondary | ghost | danger | accent
    'size' => 'md',         // sm | md | lg
    'href' => null,
    'type' => 'button',
])
@php
    $base = 'inline-flex items-center justify-center gap-2 font-semibold text-sm rounded-md transition-colors duration-[var(--dur-fast)] focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-focus disabled:opacity-45 disabled:pointer-events-none whitespace-nowrap';
    $sizes = ['sm' => 'h-8 px-3', 'md' => 'h-10 px-4', 'lg' => 'h-12 px-5 text-base'];
    $variants = [
        'primary' => 'bg-primary text-on-primary hover:bg-primary-hover',
        'secondary' => 'bg-surface text-ink border border-line-strong hover:bg-sunken',
        'ghost' => 'text-secondary hover:bg-sunken hover:text-ink',
        'danger' => 'bg-danger text-white hover:opacity-90 dark:bg-danger-bg dark:text-danger',
        'accent' => 'bg-accent text-on-accent hover:brightness-95',
    ];
    $classes = $base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']);
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</button>
@endif
