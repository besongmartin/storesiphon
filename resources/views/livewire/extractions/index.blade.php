<div class="space-y-6" @if ($polling) wire:poll.3s @endif>
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h2 class="text-2xl font-semibold tracking-[var(--tracking-tight)]">Extractions</h2>
            <p class="mt-1 text-sm text-muted">Results are kept for {{ auth()->user()->currentPlan()->retention_days }} days.</p>
        </div>
        <x-button :href="route('extractions.create')">New extraction</x-button>
    </div>

    @if ($jobs->isEmpty())
        <x-card>
            <div class="flex flex-col items-center py-10 text-center">
                <span class="size-4 rounded-xs bg-logo-record" aria-hidden="true"></span>
                <p class="mt-4 font-medium">No extractions yet</p>
                <p class="mt-1 text-sm text-muted">Start with any public WooCommerce store.</p>
                <x-button :href="route('extractions.create')" class="mt-6">New extraction</x-button>
            </div>
        </x-card>
    @else
        <div class="overflow-x-auto rounded-lg border border-line bg-surface">
            <table class="w-full text-sm">
                <caption class="sr-only">Your extractions</caption>
                <thead class="bg-sunken text-left text-xs font-semibold text-secondary">
                    <tr>
                        <th scope="col" class="px-4 py-3">Store</th>
                        <th scope="col" class="px-4 py-3">Status</th>
                        <th scope="col" class="px-4 py-3 text-right">Products</th>
                        <th scope="col" class="px-4 py-3 text-right">Variations</th>
                        <th scope="col" class="px-4 py-3">Started</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-line">
                    @foreach ($jobs as $job)
                        <tr wire:key="job-{{ $job->id }}" class="hover:bg-sunken/60">
                            <td class="px-4 py-3">
                                <a href="{{ route('extractions.show', $job) }}" class="font-medium text-ink hover:text-primary">{{ $job->store?->name ?? parse_url($job->input_url, PHP_URL_HOST) }}</a>
                                <div class="font-mono text-xs text-muted">{{ parse_url($job->input_url, PHP_URL_HOST) }}</div>
                            </td>
                            <td class="px-4 py-3"><x-badge :tone="$job->tone()">{{ ucfirst($job->status) }}</x-badge></td>
                            <td class="px-4 py-3 text-right tabular">{{ number_format($job->products_found) }}</td>
                            <td class="px-4 py-3 text-right tabular">{{ number_format($job->variations_found) }}</td>
                            <td class="px-4 py-3 text-muted" title="{{ $job->created_at }}">{{ $job->created_at->diffForHumans() }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        {{ $jobs->links() }}
    @endif
</div>
