<div class="max-w-[var(--content-max)] space-y-6">
    <div>
        <h2 class="text-2xl font-semibold tracking-[var(--tracking-tight)]">New extraction</h2>
        <p class="mt-1 text-sm text-muted">Enter the address of a public WooCommerce store. We check it first, then collect products in the background.</p>
    </div>

    <x-card>
        <form wire:submit="start" class="space-y-5">
            <x-input name="url" wire:model="url" label="Store address" placeholder="shop.example.com" mono autofocus
                hint="Only publicly available catalogue data is read. robots.txt and the store's rate limits are respected." />

            <div class="grid gap-5 sm:grid-cols-2">
                <x-input name="maxProducts" type="number" wire:model="maxProducts" label="Maximum products" min="1" :max="$limit"
                    :hint="'Your plan allows up to '.$limit.' products per extraction.'" />
                <div class="space-y-1.5">
                    <span class="block text-sm font-medium text-ink">Variations</span>
                    <label class="flex h-10 items-center gap-2 text-sm text-secondary">
                        <input type="checkbox" wire:model="includeVariations" class="size-4 rounded-xs border-line-strong accent-[var(--color-primary)]">
                        Include every variation with its own price, SKU and stock
                    </label>
                </div>
            </div>

            <div class="flex items-center gap-3">
                <x-button type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="start">Start extraction</span>
                    <span wire:loading wire:target="start">Checking…</span>
                </x-button>
                <x-button :href="route('extractions.index')" variant="ghost">Cancel</x-button>
            </div>
        </form>
    </x-card>
</div>
