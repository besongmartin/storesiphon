@php
    $docs = [
        'terms' => ['Terms of service', 'Covers acceptable use (public data only; no use that breaches a target site\'s terms or rights; no attempts to bypass access controls), plans and prepaid Bitcoin periods, limits, termination and liability.'],
        'privacy' => ['Privacy policy', 'Covers what we store (account data, extraction jobs and exports for a limited retention period, payment invoices), processors, retention, your rights and contact details.'],
        'refunds' => ['Refund policy', 'Covers prepaid Bitcoin plans: refunds to a customer-supplied address, how the amount is determined, under- and overpayments, and expired invoices.'],
        'store-owners' => ['For store owners', 'StoreSiphon only reads publicly available catalogue data, honours robots.txt and crawl delays, identifies itself, and stops at access controls. Store owners will be able to request that their domain is excluded.'],
    ];
    [$heading, $summary] = $docs[$document];
@endphp
<x-layouts.marketing :title="$heading">
    <article class="mx-auto max-w-[var(--content-max)] px-4 py-16 sm:px-6">
        <x-badge tone="warning">Draft · pending legal review</x-badge>
        <h1 class="mt-4 text-4xl font-bold tracking-[var(--tracking-tight)]">{{ $heading }}</h1>
        <p class="mt-6 text-lg text-secondary">{{ $summary }}</p>
        <p class="mt-6 text-sm text-muted">The full text will be published before StoreSiphon accepts payments.</p>
    </article>
</x-layouts.marketing>
