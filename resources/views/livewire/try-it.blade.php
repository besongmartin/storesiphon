<section class="mx-auto max-w-[var(--container-max)] px-4 py-16 sm:px-6" @if ($job?->isActive()) wire:poll.2s @endif>
    <div class="max-w-2xl">
        <p class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[var(--tracking-caps)] text-primary"><span class="record"></span> Free preview</p>
        <h1 class="mt-4 text-4xl font-bold tracking-[var(--tracking-tight)]">See what StoreSiphon finds</h1>
        <p class="mt-3 text-secondary">Enter a public WooCommerce store. We'll check it and show the first {{ config('storesiphon.limits.guest.max_products_per_job') }} products. No account needed; exports require a free account.</p>
    </div>

    @if (! $job)
        <form wire:submit="start" class="mt-8 flex max-w-2xl flex-col gap-3 sm:flex-row sm:items-start">
            <div class="flex-1">
                <x-input name="url" wire:model="url" label="Store address" placeholder="shop.example.com" mono />
                <div class="mt-3"><x-turnstile wire:model="turnstileToken" /></div>
            </div>
            <x-button type="submit" size="lg" class="sm:mt-6" wire:loading.attr="disabled">Preview store</x-button>
        </form>
    @else
        <div class="mt-8 space-y-6">
            <div class="flex flex-wrap items-center gap-3">
                <x-badge :tone="$job->tone()" role="status">{{ ucfirst($job->status) }}</x-badge>
                <span class="font-mono text-sm text-muted">{{ parse_url($job->input_url, PHP_URL_HOST) }}</span>
                <button type="button" wire:click="startOver" class="ml-auto text-sm font-medium text-primary hover:underline">Try another store</button>
            </div>

            @if ($job->isActive())
                <p class="text-secondary" role="status" aria-live="polite">{{ $job->status === 'running' ? 'Extracting products… '.$job->products_found.' found so far.' : 'Checking the store…' }}</p>
            @elseif ($job->error_message)
                <x-alert :tone="$job->products_found ? 'warning' : 'danger'">{{ $job->error_message }}</x-alert>
            @endif

            @if ($products->isNotEmpty())
                @include('livewire.extractions._preview-table', ['products' => $products])
            @endif

            @if ($job->isFinished())
                <div class="flex flex-wrap items-center justify-between gap-4 rounded-lg border border-primary bg-primary-subtle p-5">
                    <p class="text-sm text-ink">Want the full catalogue as CSV, Excel or JSON?</p>
                    <x-button :href="route('register')">Create a free account</x-button>
                </div>
            @endif
        </div>
    @endif
</section>
