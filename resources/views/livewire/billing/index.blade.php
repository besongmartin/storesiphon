<div class="space-y-8">
    <div>
        <h2 class="text-2xl font-semibold tracking-[var(--tracking-tight)]">Billing</h2>
        <p class="mt-1 text-sm text-muted">Plans are prepaid for 30 days at a time in Bitcoin. Nothing renews automatically.</p>
    </div>

    @if ($error)
        <x-alert tone="danger">{{ $error }}</x-alert>
    @endif

    {{-- Current plan + usage --}}
    @php $pct = $plan->product_quota > 0 ? min(100, round($used / $plan->product_quota * 100)) : 0; @endphp
    <x-card title="Current plan">
        <div class="grid gap-6 sm:grid-cols-2">
            <div>
                <p class="text-3xl font-semibold">{{ $plan->name }}</p>
                <p class="mt-1 text-sm text-muted">
                    @if ($subscription)
                        Paid until {{ $subscription->ends_at->toDayDateTimeString() }}
                    @else
                        Free forever · {{ number_format($plan->max_products_per_job) }} products per extraction
                    @endif
                </p>
            </div>
            <div>
                <div class="flex items-center justify-between text-sm">
                    <span class="font-medium">Products this period</span>
                    <span class="tabular text-muted">{{ number_format($used) }} / {{ number_format($plan->product_quota) }}</span>
                </div>
                <div class="mt-2 h-2 overflow-hidden rounded-xs bg-sunken" role="meter" aria-valuemin="0" aria-valuemax="{{ $plan->product_quota }}" aria-valuenow="{{ $used }}" aria-label="Products used">
                    <div class="h-full {{ $pct >= 90 ? 'bg-accent' : 'bg-primary' }}" style="width: {{ $pct }}%"></div>
                </div>
            </div>
        </div>
    </x-card>

    {{-- Plans --}}
    <section aria-labelledby="plans-heading">
        <h3 id="plans-heading" class="text-xl font-semibold">Buy 30 days</h3>
        @unless ($configured)
            <x-alert tone="warning" class="mt-4">Bitcoin payments are not configured on this server yet. Add the BTCPay settings to .env to enable checkout.</x-alert>
        @endunless
        <div class="mt-4 grid gap-4 md:grid-cols-2">
            @foreach ($plans as $p)
                <div class="flex flex-col rounded-xl border bg-surface p-6 {{ $plan->id === $p->id ? 'border-primary' : 'border-line' }}">
                    <div class="flex items-center justify-between">
                        <h4 class="text-lg font-semibold">{{ $p->name }}</h4>
                        @if ($plan->id === $p->id)<x-badge tone="success">Current</x-badge>@endif
                    </div>
                    <p class="mt-3"><span class="text-3xl font-bold tabular">${{ number_format((float) $p->price_usd, 0) }}</span> <span class="text-sm text-muted">per {{ $p->period_days }} days</span></p>
                    <ul class="mt-4 flex-1 space-y-2 text-sm text-secondary">
                        <li class="flex gap-3"><span class="record mt-1.5"></span>{{ number_format($p->product_quota) }} products per period</li>
                        <li class="flex gap-3"><span class="record mt-1.5"></span>Up to {{ number_format($p->max_products_per_job) }} products per extraction</li>
                        <li class="flex gap-3"><span class="record mt-1.5"></span>{{ $p->concurrent_jobs }} extraction{{ $p->concurrent_jobs > 1 ? 's' : '' }} at a time · results kept {{ $p->retention_days }} days</li>
                    </ul>
                    <x-button wire:click="buy('{{ $p->slug }}')" variant="accent" class="mt-6 w-full" :disabled="! $configured" wire:loading.attr="disabled">
                        <svg width="16" height="16" viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M11.5 3v2H9v14h2.5v2h1.5v-2h1c2.2 0 4-1.6 4-3.8 0-1.5-.9-2.7-2.2-3.3 1-.6 1.6-1.6 1.6-2.8C17.4 6.6 15.8 5 13.6 5H13V3h-1.5Zm-.5 4h2.4c1 0 1.9.8 1.9 1.9S14.4 10.8 13.4 10.8H11V7Zm0 5.8h3c1.2 0 2.1.9 2.1 2.1S15.2 17 14 17h-3v-4.2Z"/></svg>
                        Pay with Bitcoin {{ $plan->id === $p->id ? '(extend)' : '' }}
                    </x-button>
                </div>
            @endforeach
        </div>
        <p class="mt-3 text-xs text-muted">Payments are processed by BTCPay Server and go directly to StoreSiphon's own wallet. Access starts once the payment has 1 on-chain confirmation (usually 10–20 minutes).</p>
    </section>

    {{-- Invoices --}}
    <section aria-labelledby="invoices-heading" class="space-y-3">
        <h3 id="invoices-heading" class="text-xl font-semibold">Invoices</h3>
        @if ($invoices->isEmpty())
            <p class="text-sm text-muted">No invoices yet.</p>
        @else
            <div class="overflow-x-auto rounded-lg border border-line bg-surface">
                <table class="w-full text-sm">
                    <caption class="sr-only">Your invoices</caption>
                    <thead class="bg-sunken text-left text-xs font-semibold text-secondary">
                        <tr><th scope="col" class="px-4 py-3">Date</th><th scope="col" class="px-4 py-3">Plan</th><th scope="col" class="px-4 py-3 text-right">Amount</th><th scope="col" class="px-4 py-3">Status</th><th scope="col" class="px-4 py-3">Reference</th></tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($invoices as $invoice)
                            <tr class="hover:bg-sunken/60">
                                <td class="px-4 py-3">{{ $invoice->created_at->toFormattedDateString() }}</td>
                                <td class="px-4 py-3">{{ $invoice->plan->name }}</td>
                                <td class="px-4 py-3 text-right tabular">${{ number_format((float) $invoice->fiat_amount, 2) }}@if ($invoice->btc_amount)<span class="block font-mono text-xs text-muted">{{ $invoice->btc_amount }} BTC</span>@endif</td>
                                <td class="px-4 py-3"><x-badge :tone="$invoice->tone()">{{ $invoice->label() }}</x-badge></td>
                                <td class="px-4 py-3"><a href="{{ route('billing.invoices.show', $invoice) }}" class="font-mono text-xs text-primary hover:underline">{{ \Illuminate\Support\Str::limit($invoice->id, 13, '…') }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
