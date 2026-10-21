<div class="max-w-[var(--content-max)] space-y-6" @if ($invoice->isOpen()) wire:poll.5s="check" @endif>
    @php
        $steps = [
            ['key' => 'pending', 'label' => 'Invoice created'],
            ['key' => 'detected', 'label' => 'Payment detected'],
            ['key' => 'confirming', 'label' => 'Confirming'],
            ['key' => 'confirmed', 'label' => 'Paid'],
        ];
        $rank = ['pending' => 0, 'detected' => 1, 'confirming' => 2, 'confirmed' => 3, 'overpaid' => 3];
        $current = $rank[$invoice->status] ?? null;
    @endphp

    <div>
        <a href="{{ route('billing.index') }}" class="text-sm text-muted hover:text-ink">← Billing</a>
        <div class="mt-1 flex flex-wrap items-center gap-3">
            <h2 class="text-2xl font-semibold tracking-[var(--tracking-tight)]">{{ $invoice->plan->name }} · {{ $invoice->plan->period_days }} days</h2>
            <x-badge :tone="$invoice->tone()" role="status">{{ $invoice->label() }}</x-badge>
        </div>
        <p class="mt-1 font-mono text-xs text-muted">Reference {{ $invoice->id }}</p>
    </div>

    @if ($current !== null)
        <ol class="grid grid-cols-4 gap-2" aria-label="Payment progress">
            @foreach ($steps as $i => $step)
                <li class="space-y-2">
                    <div class="h-1.5 rounded-xs {{ $i <= $current ? 'bg-primary' : 'bg-sunken' }}"></div>
                    <p class="text-xs {{ $i <= $current ? 'font-semibold text-ink' : 'text-muted' }}">{{ $step['label'] }}</p>
                </li>
            @endforeach
        </ol>
    @endif

    @if ($invoice->isPaid())
        <x-alert tone="success" title="Payment confirmed">Your {{ $invoice->plan->name }} plan is active. Thank you!</x-alert>
    @elseif ($invoice->status === 'underpaid')
        <x-alert tone="warning" title="Partial payment received">The invoice expired before the full amount arrived. Our team has been notified and will contact you about the difference or a refund.</x-alert>
    @elseif ($invoice->status === 'expired')
        <x-alert tone="warning" title="Invoice expired">No payment was received in time. If you sent a payment after it expired, it is flagged for review. You can create a new invoice from Billing.</x-alert>
    @elseif ($invoice->status === 'failed')
        <x-alert tone="danger" title="Payment failed">This invoice could not be completed. You have not been charged for StoreSiphon access.</x-alert>
    @endif

    <x-card>
        <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
            <div><dt class="text-xs font-semibold uppercase tracking-[var(--tracking-caps)] text-muted">Amount</dt><dd class="mt-1 text-lg font-semibold tabular">${{ number_format((float) $invoice->fiat_amount, 2) }} {{ $invoice->fiat_currency }}</dd></div>
            <div><dt class="text-xs font-semibold uppercase tracking-[var(--tracking-caps)] text-muted">In Bitcoin</dt><dd class="mt-1 font-mono text-lg tabular">{{ $invoice->btc_amount ?? '—' }} BTC</dd></div>
            <div><dt class="text-xs font-semibold uppercase tracking-[var(--tracking-caps)] text-muted">Exchange rate</dt><dd class="mt-1 tabular">{{ $invoice->rate ? '$'.number_format((float) $invoice->rate, 2).' / BTC' : '—' }}@if ($invoice->rate_at)<span class="block text-xs text-muted">fixed at {{ $invoice->rate_at->toDayDateTimeString() }}</span>@endif</dd></div>
            <div><dt class="text-xs font-semibold uppercase tracking-[var(--tracking-caps)] text-muted">Received</dt><dd class="mt-1 font-mono tabular">{{ $invoice->btc_paid ?? '0' }} BTC</dd></div>
        </dl>

        @if ($invoice->isOpen() && $invoice->checkout_url)
            <div class="mt-6 flex flex-wrap items-center gap-4 border-t border-line pt-6">
                <x-button :href="$invoice->checkout_url" variant="accent" target="_blank" rel="noopener noreferrer">Open payment page</x-button>
                @if ($invoice->expires_at)
                    <span class="text-sm text-muted" x-data="{ s: {{ max(0, now()->diffInSeconds($invoice->expires_at, false)) }} }" x-init="setInterval(() => s = Math.max(0, s - 1), 1000)" aria-live="off">
                        Expires in <span class="font-mono tabular" x-text="Math.floor(s/60) + ':' + String(s%60).padStart(2,'0')"></span>
                    </span>
                @endif
            </div>
            <p class="mt-3 text-xs text-muted">Pay the exact BTC amount shown on the payment page. This page updates by itself; access activates after 1 on-chain confirmation — never just from returning here.</p>
        @endif
    </x-card>
</div>
