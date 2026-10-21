<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h2 class="text-2xl font-semibold tracking-[var(--tracking-tight)]">Payments</h2>
            <p class="mt-1 text-sm text-muted">Bitcoin invoices, reconciliation and the review queue. Every decision is written to the audit log.</p>
        </div>
        <div class="inline-flex rounded-md border border-line bg-surface p-0.5" role="radiogroup" aria-label="Filter invoices">
            @foreach (['review' => 'Needs review ('.$reviewCount.')', 'open' => 'Open', 'all' => 'All'] as $value => $label)
                <button type="button" role="radio" wire:click="$set('filter', '{{ $value }}')" aria-checked="{{ $filter === $value ? 'true' : 'false' }}"
                    class="h-8 rounded-sm px-3 text-xs font-semibold {{ $filter === $value ? 'bg-primary-subtle text-primary' : 'text-secondary hover:text-ink' }}">{{ $label }}</button>
            @endforeach
        </div>
    </div>

    @if ($message)
        <x-alert tone="info">{{ $message }}</x-alert>
    @endif

    <div class="overflow-x-auto rounded-lg border border-line bg-surface">
        <table class="w-full text-sm">
            <caption class="sr-only">Invoices</caption>
            <thead class="bg-sunken text-left text-xs font-semibold text-secondary">
                <tr>
                    <th scope="col" class="px-4 py-3">Created</th><th scope="col" class="px-4 py-3">Customer</th><th scope="col" class="px-4 py-3">Plan</th>
                    <th scope="col" class="px-4 py-3 text-right">Requested / received</th><th scope="col" class="px-4 py-3">Status</th><th scope="col" class="px-4 py-3">Provider</th><th scope="col" class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-line">
                @forelse ($invoices as $invoice)
                    <tr wire:key="inv-{{ $invoice->id }}" class="{{ $invoice->needs_review ? 'bg-warning-bg/40' : '' }}">
                        <td class="px-4 py-3 whitespace-nowrap">{{ $invoice->created_at->format('Y-m-d H:i') }}</td>
                        <td class="px-4 py-3">{{ $invoice->user->email }}</td>
                        <td class="px-4 py-3">{{ $invoice->plan->name }}</td>
                        <td class="px-4 py-3 text-right font-mono text-xs tabular">{{ $invoice->btc_amount ?? '—' }}<br>{{ $invoice->btc_paid ?? '0' }}</td>
                        <td class="px-4 py-3">
                            <x-badge :tone="$invoice->tone()">{{ $invoice->label() }}</x-badge>
                            @if ($invoice->granted_at)<span class="ml-1 text-xs text-success">granted</span>@endif
                            @if ($invoice->needs_review)<p class="mt-1 text-xs text-warning">{{ $invoice->review_note }}</p>@endif
                        </td>
                        <td class="px-4 py-3 font-mono text-xs text-muted">{{ $invoice->provider_status }}{{ $invoice->provider_additional_status ? ' / '.$invoice->provider_additional_status : '' }}</td>
                        <td class="px-4 py-3 text-right whitespace-nowrap">
                            <x-button wire:click="recheck('{{ $invoice->id }}')" variant="ghost" size="sm">Re-check</x-button>
                            <x-button wire:click="select('{{ $invoice->id }}')" variant="secondary" size="sm">{{ $selected === $invoice->id ? 'Close' : 'Review' }}</x-button>
                        </td>
                    </tr>
                    @if ($selected === $invoice->id)
                        <tr class="bg-sunken/50">
                            <td colspan="7" class="px-4 py-4">
                                <div class="grid gap-6 lg:grid-cols-2">
                                    <div class="space-y-3">
                                        <label for="note" class="block text-sm font-medium">Decision note (required)</label>
                                        <textarea id="note" wire:model="note" rows="3" class="block w-full rounded-md border border-line-strong bg-surface p-3 text-sm focus:border-focus focus:outline-none"
                                            placeholder="e.g. Customer topped up the missing amount in a separate transaction (txid …)"></textarea>
                                        @error('note')<p class="text-xs text-danger">{{ $message }}</p>@enderror
                                        <div class="flex flex-wrap gap-2">
                                            @unless ($invoice->granted_at)
                                                <x-button wire:click="resolve('{{ $invoice->id }}', true)" wire:confirm="Grant {{ $invoice->plan->name }} for {{ $invoice->plan->period_days }} days to {{ $invoice->user->email }}?" size="sm">Resolve and grant period</x-button>
                                            @endunless
                                            <x-button wire:click="resolve('{{ $invoice->id }}', false)" variant="secondary" size="sm">Resolve without access</x-button>
                                        </div>
                                        <p class="text-xs text-muted">Refunds are sent from the BTCPay wallet to an address the customer provides; record the refund transaction here.</p>
                                    </div>
                                    <div>
                                        <p class="text-sm font-medium">Audit trail</p>
                                        <ul class="mt-2 space-y-2 text-xs">
                                            @forelse ($history as $entry)
                                                <li class="rounded-md border border-line bg-surface p-2">
                                                    <span class="font-mono">{{ $entry->created_at }}</span> · <strong>{{ $entry->action }}</strong> · {{ $entry->actor?->email ?? 'system' }}
                                                    @if ($entry->note)<p class="mt-1 text-secondary">{{ $entry->note }}</p>@endif
                                                    @if ($entry->after)<p class="mt-1 font-mono text-muted">{{ json_encode($entry->after) }}</p>@endif
                                                </li>
                                            @empty
                                                <li class="text-muted">No entries.</li>
                                            @endforelse
                                        </ul>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-muted">Nothing here.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $invoices->links() }}
</div>
