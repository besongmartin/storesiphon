@props(['tone' => 'info', 'title' => null]) {{-- info | success | warning | danger --}}
@php
    $tones = [
        'info' => 'bg-info-bg text-info border-info/30',
        'success' => 'bg-success-bg text-success border-success/30',
        'warning' => 'bg-warning-bg text-warning border-warning/30',
        'danger' => 'bg-danger-bg text-danger border-danger/30',
    ];
    $role = $tone === 'danger' ? 'alert' : 'status';
@endphp
<div role="{{ $role }}" {{ $attributes->merge(['class' => 'rounded-md border px-4 py-3 text-sm '.($tones[$tone] ?? $tones['info'])]) }}>
    @if ($title)
        <p class="font-semibold">{{ $title }}</p>
    @endif
    <div class="text-ink/90">{{ $slot }}</div>
</div>
