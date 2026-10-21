@props([
    'variant' => 'horizontal', // horizontal | symbol | stacked
    'animated' => false,       // intro animation (marketing header, first view only)
    'height' => 28,
])
@php
    $ratio = ['horizontal' => 1034 / 256, 'symbol' => 1, 'stacked' => 1]; // width / height of the artwork
    $w = (int) round($height * ($ratio[$variant] ?? 1));
    $dir = $animated && $variant !== 'stacked' ? 'animation' : 'logo';
    $name = fn (string $tone) => $animated && $variant !== 'stacked'
        ? "storesiphon-{$variant}-animated".($tone === 'dark' ? '-on-dark' : '').'.svg'
        : "storesiphon-{$variant}-".($tone === 'dark' ? 'color-on-dark' : 'color').'.svg';
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center']) }}>
    <img src="/brand/{{ $dir }}/{{ $name('light') }}" width="{{ $w }}" height="{{ $height }}" alt="StoreSiphon" class="logo-light">
    <img src="/brand/{{ $dir }}/{{ $name('dark') }}" width="{{ $w }}" height="{{ $height }}" alt="" aria-hidden="true" class="logo-dark">
</span>
