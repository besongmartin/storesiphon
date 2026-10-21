{{-- Product preview table. Expects $products (paginator or collection of top-level Product with variations). --}}
@php
    $money = function ($p) {
        $fmt = fn ($v) => $v === null ? null : number_format((float) $v, 2);
        if ($p->type === 'variable') {
            return $p->price_min !== null ? $fmt($p->price_min).($p->price_max !== null && $p->price_max != $p->price_min ? ' – '.$fmt($p->price_max) : '') : '—';
        }
        return $fmt($p->price) ?? '—';
    };
    $stockTone = ['instock' => 'success', 'outofstock' => 'danger', 'onbackorder' => 'warning'];
    $stockLabel = ['instock' => 'In stock', 'outofstock' => 'Out of stock', 'onbackorder' => 'Backorder', 'unknown' => 'Unknown'];
@endphp
<div class="overflow-x-auto rounded-lg border border-line bg-surface">
    <table class="w-full text-sm">
        <caption class="sr-only">Extracted products</caption>
        <thead class="bg-sunken text-left text-xs font-semibold text-secondary">
            <tr>
                <th scope="col" class="w-14 px-4 py-3"><span class="sr-only">Image</span></th>
                <th scope="col" class="px-4 py-3">Product</th>
                <th scope="col" class="px-4 py-3">SKU</th>
                <th scope="col" class="px-4 py-3 text-right">Price</th>
                <th scope="col" class="px-4 py-3">Stock</th>
                <th scope="col" class="px-4 py-3">Category</th>
            </tr>
        </thead>
        @forelse ($products as $product)
            <tbody x-data="{ open: false }" wire:key="product-{{ $product->id }}" class="border-t border-line">
                <tr class="hover:bg-sunken/60">
                    <td class="px-4 py-2">
                        @if ($img = $product->images[0]['src'] ?? null)
                            <img src="{{ $img }}" alt="" loading="lazy" referrerpolicy="no-referrer" class="size-10 rounded-sm bg-white object-contain">
                        @else
                            <span class="block size-10 rounded-sm bg-sunken"></span>
                        @endif
                    </td>
                    <td class="px-4 py-2">
                        <div class="font-medium text-ink">{{ $product->name }}</div>
                        <div class="mt-0.5 flex flex-wrap items-center gap-2 text-xs text-muted">
                            <span>{{ ucfirst($product->type) }}</span>
                            @if ($product->variations->isNotEmpty())
                                <button type="button" x-on:click="open = !open" x-bind:aria-expanded="open" class="font-medium text-primary hover:underline">
                                    <span x-text="open ? 'Hide' : 'Show'">Show</span> {{ $product->variations->count() }} variations
                                </button>
                            @endif
                            @if ($product->isOnSale())<x-badge tone="warning">Sale</x-badge>@endif
                        </div>
                    </td>
                    <td class="px-4 py-2 font-mono text-xs">{{ $product->sku ?? '—' }}</td>
                    <td class="px-4 py-2 text-right tabular whitespace-nowrap">
                        @if ($product->isOnSale())<del class="mr-1 text-muted">{{ number_format((float) $product->regular_price, 2) }}</del>@endif
                        {{ $money($product) }} <span class="text-xs text-muted">{{ $product->currency }}</span>
                    </td>
                    <td class="px-4 py-2"><x-badge :tone="$stockTone[$product->stock_status] ?? 'neutral'">{{ $stockLabel[$product->stock_status] ?? $product->stock_status }}{{ $product->stock_quantity !== null ? ' · '.$product->stock_quantity : '' }}</x-badge></td>
                    <td class="px-4 py-2 text-secondary">{{ collect($product->categories)->pluck('name')->implode(', ') ?: '—' }}</td>
                </tr>
                @foreach ($product->variations as $variation)
                    <tr x-cloak x-show="open" class="bg-sunken/40 text-xs">
                        <td></td>
                        <td class="py-2 pl-8 pr-4"><span class="record mr-2 align-middle"></span>{{ collect($variation->variation_attributes)->map(fn ($v, $k) => "$k: $v")->implode(', ') ?: $variation->name }}</td>
                        <td class="px-4 py-2 font-mono">{{ $variation->sku ?? '—' }}</td>
                        <td class="px-4 py-2 text-right tabular whitespace-nowrap">
                            @if ($variation->isOnSale())<del class="mr-1 text-muted">{{ number_format((float) $variation->regular_price, 2) }}</del>@endif
                            {{ $money($variation) }}
                        </td>
                        <td class="px-4 py-2"><x-badge :tone="$stockTone[$variation->stock_status] ?? 'neutral'">{{ $stockLabel[$variation->stock_status] ?? $variation->stock_status }}{{ $variation->stock_quantity !== null ? ' · '.$variation->stock_quantity : '' }}</x-badge></td>
                        <td></td>
                    </tr>
                @endforeach
            </tbody>
        @empty
            <tbody><tr><td colspan="6" class="px-4 py-10 text-center text-muted">No products match.</td></tr></tbody>
        @endforelse
    </table>
</div>
