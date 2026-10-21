@php
    $steps = [
        ['Point it at a store', 'Paste the address of any public WooCommerce shop. A quick check confirms the store and what it exposes before you spend anything.'],
        ['We draw the catalogue', 'Products, prices, stock, categories, images and every variation are collected in the background, politely and within the store\'s crawl rules.'],
        ['Get structured data', 'Preview, search and filter the results, then export clean files: one row per variant, prices as numbers, no stray HTML.'],
    ];
    $formats = ['CSV', 'Excel (XLSX)', 'JSON', 'Shopify CSV', 'WooCommerce CSV'];
    $plans = [
        ['Free', '$0', 'to try it out', ['25 products per export', '3 stores per day', 'CSV, XLSX and JSON'], false],
        ['Starter', '$19', 'per 30 days', ['5,000 products per month', 'Unlimited stores', 'CSV, XLSX and JSON'], false],
        ['Pro', '$49', 'per 30 days', ['50,000 products per month', 'Shopify and WooCommerce formats', 'Priority extraction'], true],
    ];
@endphp
<x-layouts.marketing>
    {{-- Hero --}}
    <section class="mx-auto max-w-[var(--container-max)] px-4 pt-20 pb-16 sm:px-6 lg:pt-28">
        <div class="max-w-3xl">
            <p class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[var(--tracking-caps)] text-primary"><span class="record"></span> WooCommerce product data</p>
            <h1 class="mt-5 text-4xl font-bold leading-[var(--leading-tight)] tracking-[var(--tracking-tight)] sm:text-5xl">Point it at a store. Get the catalogue, structured.</h1>
            <p class="mt-6 max-w-2xl text-lg leading-[var(--leading-normal)] text-secondary">StoreSiphon reads the public catalogue of a WooCommerce shop and hands it back as clean, consistent data: prices, stock, variations and images included.</p>
            <div class="mt-8 flex flex-wrap gap-3">
                <x-button :href="route('register')" size="lg">Start free</x-button>
                <x-button href="#how-it-works" variant="secondary" size="lg">How it works</x-button>
            </div>
            <p class="mt-4 text-sm text-muted">No card needed. Paid plans are prepaid in Bitcoin.</p>
        </div>
    </section>

    {{-- How it works --}}
    <section id="how-it-works" class="border-y border-line bg-surface">
        <div class="mx-auto max-w-[var(--container-max)] px-4 py-20 sm:px-6">
            <h2 class="text-3xl font-semibold tracking-[var(--tracking-tight)]">How it works</h2>
            <ol class="mt-10 grid gap-8 md:grid-cols-3">
                @foreach ($steps as $i => [$title, $text])
                    <li>
                        <span class="font-mono text-sm text-primary tabular">0{{ $i + 1 }}</span>
                        <h3 class="mt-2 text-xl font-semibold">{{ $title }}</h3>
                        <p class="mt-2 text-secondary">{{ $text }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Formats + principles --}}
    <section id="formats" class="mx-auto max-w-[var(--container-max)] px-4 py-20 sm:px-6">
        <div class="grid gap-12 lg:grid-cols-2">
            <div>
                <h2 class="text-3xl font-semibold tracking-[var(--tracking-tight)]">Exports that import cleanly</h2>
                <p class="mt-4 text-secondary">Variations stay variations. Sale prices only appear when something is on sale. Files open correctly in Excel, accents included.</p>
                <ul class="mt-6 flex flex-wrap gap-2">
                    @foreach ($formats as $format)
                        <li class="inline-flex items-center gap-2 rounded-md border border-line bg-surface px-3 py-1.5 text-sm"><span class="record"></span>{{ $format }}</li>
                    @endforeach
                </ul>
            </div>
            <div class="rounded-lg border border-line bg-surface p-6">
                <h3 class="text-xl font-semibold">Built to be a good guest</h3>
                <ul class="mt-4 space-y-3 text-sm text-secondary">
                    <li class="flex gap-3"><span class="record mt-1.5"></span>Reads only what a store already makes public.</li>
                    <li class="flex gap-3"><span class="record mt-1.5"></span>Follows robots.txt and crawl delays, with a strict per-store request limit.</li>
                    <li class="flex gap-3"><span class="record mt-1.5"></span>Stops at logins, blocks and challenges instead of trying to get around them.</li>
                    <li class="flex gap-3"><span class="record mt-1.5"></span>Store owners can ask to be excluded.</li>
                </ul>
            </div>
        </div>
    </section>

    {{-- Pricing --}}
    <section id="pricing" class="border-t border-line bg-surface">
        <div class="mx-auto max-w-[var(--container-max)] px-4 py-20 sm:px-6">
            <h2 class="text-3xl font-semibold tracking-[var(--tracking-tight)]">Simple, prepaid plans</h2>
            <p class="mt-3 text-secondary">Pay for 30 days at a time in Bitcoin. Access starts once the payment is confirmed on-chain. No subscriptions to cancel.</p>
            <div class="mt-10 grid gap-6 md:grid-cols-3">
                @foreach ($plans as [$name, $price, $period, $features, $featured])
                    <div class="flex flex-col rounded-xl border bg-bg p-6 {{ $featured ? 'border-primary shadow-md' : 'border-line' }}">
                        <div class="flex items-center justify-between">
                            <h3 class="text-xl font-semibold">{{ $name }}</h3>
                            @if ($featured)<x-badge tone="info">Most capacity</x-badge>@endif
                        </div>
                        <p class="mt-4"><span class="text-4xl font-bold tabular">{{ $price }}</span> <span class="text-sm text-muted">{{ $period }}</span></p>
                        <ul class="mt-6 flex-1 space-y-2 text-sm text-secondary">
                            @foreach ($features as $feature)
                                <li class="flex gap-3"><span class="record mt-1.5"></span>{{ $feature }}</li>
                            @endforeach
                        </ul>
                        <x-button :href="route('register')" :variant="$featured ? 'primary' : 'secondary'" class="mt-8 w-full">{{ $name === 'Free' ? 'Start free' : 'Choose '.$name }}</x-button>
                    </div>
                @endforeach
            </div>
            <p class="mt-6 text-xs text-muted">Indicative launch prices. Final prices are confirmed at checkout.</p>
        </div>
    </section>
</x-layouts.marketing>
