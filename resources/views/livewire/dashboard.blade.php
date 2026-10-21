<div class="space-y-8" @if ($polling) wire:poll.3s @endif>
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-2xl font-semibold tracking-[var(--tracking-tight)]">Welcome, {{ auth()->user()->name }}</h2>
            <p class="mt-1 text-sm text-muted">Point StoreSiphon at a WooCommerce store to get its catalogue as structured data.</p>
        </div>
        <x-button :href="route('extractions.create')">New extraction</x-button>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        @foreach ($stats as $stat)
            <div class="rounded-lg border border-line bg-surface p-5">
                <p class="text-xs font-semibold uppercase tracking-[var(--tracking-caps)] text-muted">{{ $stat['label'] }}</p>
                <p class="mt-2 text-3xl font-semibold tabular">{{ $stat['value'] }}</p>
                <p class="mt-1 text-xs text-muted">{{ $stat['hint'] }}</p>
            </div>
        @endforeach
    </div>

    <x-card title="Recent extractions" padding="p-0">
        @if ($recent->isEmpty())
            <div class="flex flex-col items-center px-6 py-10 text-center">
                <span class="size-4 rounded-xs bg-logo-record" aria-hidden="true"></span>
                <p class="mt-4 font-medium">No extractions yet</p>
                <p class="mt-1 max-w-sm text-sm text-muted">Start with any public WooCommerce store. We check it first and tell you exactly what we can read.</p>
                <x-button :href="route('extractions.create')" class="mt-6">New extraction</x-button>
            </div>
        @else
            <ul class="divide-y divide-line">
                @foreach ($recent as $job)
                    <li>
                        <a href="{{ route('extractions.show', $job) }}" class="flex flex-wrap items-center justify-between gap-3 px-6 py-4 hover:bg-sunken/60">
                            <span>
                                <span class="block font-medium">{{ $job->store?->name ?? parse_url($job->input_url, PHP_URL_HOST) }}</span>
                                <span class="text-xs text-muted">{{ $job->created_at->diffForHumans() }} · {{ number_format($job->products_found) }} products</span>
                            </span>
                            <x-badge :tone="$job->tone()">{{ ucfirst($job->status) }}</x-badge>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-card>
</div>
