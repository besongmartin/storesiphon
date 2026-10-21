@props(['tone' => 'neutral']) {{-- neutral | info | success | warning | danger --}}
@php
    $tones = [
        'neutral' => 'bg-sunken text-secondary',
        'info' => 'bg-info-bg text-info',
        'success' => 'bg-success-bg text-success',
        'warning' => 'bg-warning-bg text-warning',
        'danger' => 'bg-danger-bg text-danger',
    ];
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-xs px-2 py-0.5 text-xs font-semibold '.($tones[$tone] ?? $tones['neutral'])]) }}>
    <span class="size-1.5 rounded-[1px] bg-current" aria-hidden="true"></span>{{ $slot }}
</span>
