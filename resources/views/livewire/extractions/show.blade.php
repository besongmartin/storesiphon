<div class="space-y-6" @if ($polling) wire:poll.2s @endif>
    @php
        $host = parse_url($job->input_url, PHP_URL_HOST);
        $pf = $job->preflight ?? [];
    @endphp

    {{-- Header --}}
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <a href="{{ route('extractions.index') }}" class="text-sm text-muted hover:text-ink">← Extractions</a>
            <h2 class="mt-1 text-2xl font-semibold tracking-[var(--tracking-tight)]">{{ $job->store?->name ?? $host }}</h2>
            <p class="font-mono text-xs text-muted">{{ $pf['origin'] ?? $job->input_url }}</p>
        </div>
        <div class="flex items-center gap-3">
            <x-badge :tone="$job->tone()" role="status">{{ ucfirst($job->status) }}</x-badge>
            @can('cancel', $job)
                <x-button wire:click="cancel" wire:confirm="Stop this extraction? Products found so far are kept." variant="secondary" size="sm"
                    :disabled="$job->cancel_requested_at !== null">{{ $job->cancel_requested_at ? 'Stopping…' : 'Cancel' }}</x-button>
            @endcan
        </div>
    </div>

    {{-- Progress / outcome --}}
    @if ($job->isActive())
        <x-card>
            <div class="space-y-3" role="status" aria-live="polite">
                <div class="flex items-center justify-between text-sm">
                    <span class="font-medium">{{ $job->status === 'queued' ? 'Waiting for a worker…' : ($job->status === 'preflight' ? 'Checking the store…' : 'Extracting products…') }}</span>
                    <span class="tabular text-muted">{{ number_format($job->products_found) }} / {{ number_format(min($job->maxProducts(), $job->products_estimated ?? $job->maxProducts())) }} products · {{ number_format($job->variations_found) }} variations</span>
                </div>
                <div class="h-2 overflow-hidden rounded-xs bg-sunken">
                    <div class="h-full bg-primary transition-[width] duration-[var(--dur-slow)]" style="width: {{ $job->progressPercent() ?? 0 }}%"></div>
                </div>
                <p class="text-xs text-muted">You can leave this page; the extraction keeps running. Requests to the store are spaced out to respect its limits.</p>
            </div>
        </x-card>
    @elseif ($job->error_message)
        <x-alert :tone="in_array($job->status, ['blocked', 'failed']) ? 'danger' : 'warning'" :title="match ($job->status) { 'blocked' => 'Extraction stopped', 'failed' => 'Extraction failed', default => 'Finished with warnings' }">
            {{ $job->error_message }}
        </x-alert>
    @endif

    {{-- Facts --}}
    <div class="grid gap-4 sm:grid-cols-4">
        @foreach ([
            ['Products', number_format($job->products_found)],
            ['Variations', number_format($job->variations_found)],
            ['Method', $job->methods_used ? str_replace(['store_api', 'rest_route', 'sitemap', ','], ['Store API', 'Store API (query)', 'Product pages', ', '], $job->methods_used) : '—'],
            ['Currency', $pf['currency'] ?? '—'],
        ] as [$label, $value])
            <div class="rounded-lg border border-line bg-surface p-4">
                <p class="text-xs font-semibold uppercase tracking-[var(--tracking-caps)] text-muted">{{ $label }}</p>
                <p class="mt-1 text-lg font-semibold tabular">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    @foreach (array_merge($pf['notes'] ?? [], $job->warnings ?? []) as $note)
        <p class="flex gap-2 text-sm text-secondary"><span class="record mt-1.5"></span>{{ $note }}</p>
    @endforeach

    {{-- Exports --}}
    @if ($job->hasResults())
        <x-card title="Export">
            <div class="flex flex-wrap items-center gap-3">
                @foreach (['csv' => 'CSV', 'xlsx' => 'Excel (XLSX)', 'json' => 'JSON'] as $format => $label)
                    <x-button wire:click="export('{{ $format }}')" variant="secondary" size="sm" wire:loading.attr="disabled">{{ $label }}</x-button>
                @endforeach
                <label class="ml-auto flex items-center gap-2 text-sm text-secondary">
                    <input type="checkbox" wire:model.live="htmlDescriptions" class="size-4 accent-[var(--color-primary)]"> Keep HTML in descriptions
                </label>
            </div>
            @if ($exports->isNotEmpty())
                <ul class="mt-5 divide-y divide-line border-t border-line text-sm">
                    @foreach ($exports as $export)
                        <li class="flex flex-wrap items-center justify-between gap-3 py-3" wire:key="export-{{ $export->id }}">
                            <span class="flex items-center gap-3">
                                <span class="font-mono text-xs uppercase">{{ $export->format }}</span>
                                <span class="text-muted">{{ $export->created_at->diffForHumans() }}</span>
                            </span>
                            @if ($export->isReady())
                                <span class="flex items-center gap-3">
                                    <span class="text-xs text-muted tabular">{{ number_format($export->row_count) }} rows · {{ number_format($export->size_bytes / 1024, 1) }} KB</span>
                                    <x-button :href="route('exports.download', $export)" size="sm">Download</x-button>
                                </span>
                            @elseif ($export->status === 'failed')
                                <x-badge tone="danger">{{ $export->error ?? 'Failed' }}</x-badge>
                            @else
                                <x-badge tone="info">Preparing…</x-badge>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    @endif

    {{-- Preview --}}
    @if ($job->products_found > 0)
        <section class="space-y-4" aria-labelledby="preview-heading">
            <div class="flex flex-wrap items-end justify-between gap-3">
                <h3 id="preview-heading" class="text-xl font-semibold">Products</h3>
                <div class="flex flex-wrap gap-2">
                    <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search name or SKU" aria-label="Search products"
                        class="h-9 w-56 rounded-md border border-line-strong bg-surface px-3 text-sm focus:border-focus focus:outline-none">
                    <select wire:model.live="type" aria-label="Product type" class="h-9 rounded-md border border-line-strong bg-surface px-2 text-sm">
                        <option value="">All types</option><option value="simple">Simple</option><option value="variable">Variable</option>
                    </select>
                    <select wire:model.live="stock" aria-label="Stock status" class="h-9 rounded-md border border-line-strong bg-surface px-2 text-sm">
                        <option value="">Any stock</option><option value="instock">In stock</option><option value="outofstock">Out of stock</option><option value="onbackorder">Backorder</option>
                    </select>
                </div>
            </div>
            @include('livewire.extractions._preview-table', ['products' => $products])
            {{ $products->links() }}
        </section>
    @endif
</div>
