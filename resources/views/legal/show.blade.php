@php
    $titles = ['terms' => 'Terms of service', 'privacy' => 'Privacy policy', 'refunds' => 'Refund policy'];
    $heading = $titles[$document];
@endphp
<x-layouts.marketing :title="$heading">
    <article class="mx-auto max-w-[var(--content-max)] px-4 py-16 sm:px-6">
        <x-badge tone="warning">Draft · pending legal review</x-badge>
        <h1 class="mt-4 text-4xl font-bold tracking-[var(--tracking-tight)]">{{ $heading }}</h1>
        <p class="mt-2 text-sm text-muted">Version 0.1 · last updated {{ \Illuminate\Support\Carbon::parse('2026-10-21')->toFormattedDateString() }}</p>
        <div class="legal mt-10 space-y-8 text-secondary [&_h2]:text-xl [&_h2]:font-semibold [&_h2]:text-ink [&_p]:mt-3 [&_li]:mt-2 [&_ul]:mt-3 [&_ul]:list-disc [&_ul]:pl-5">
            @include('legal.'.$document)
        </div>
    </article>
</x-layouts.marketing>
