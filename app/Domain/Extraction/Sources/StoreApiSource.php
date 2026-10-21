<?php

namespace App\Domain\Extraction\Sources;

use App\Domain\Extraction\Data\ProductData;
use App\Domain\Extraction\ExtractionOptions;
use App\Domain\Extraction\Support\Html;
use App\Domain\Extraction\Support\Price;
use App\Domain\Fetching\Exceptions\FetchException;
use App\Domain\Fetching\Exceptions\FetchFailedException;
use App\Domain\Fetching\FetchResponse;
use App\Domain\Fetching\SafeHttpClient;
use Generator;

/**
 * WooCommerce Store API (public, read-only): /wp-json/wc/store/v1/… or, on sites without pretty
 * permalinks, /?rest_route=/wc/store/v1/… (same API, different URL form).
 *
 * Variable products are listed with variation IDs only; each variation's SKU, prices and stock come
 * from GET products/{id}.
 */
final class StoreApiSource implements ProductSource
{
    public const PRETTY = 'store_api';

    public const QUERY = 'rest_route';

    private const BATCH_SIZE = 10;

    public function __construct(
        private readonly SafeHttpClient $client,
        private readonly string $origin,
        private readonly string $form = self::PRETTY,
    ) {}

    public function method(): string
    {
        return $this->form;
    }

    public function url(string $route, array $query = []): string
    {
        $route = ltrim($route, '/');
        if ($this->form === self::PRETTY) {
            return $this->origin.'/wp-json/wc/store/v1/'.$route.($query ? '?'.http_build_query($query) : '');
        }

        return $this->origin.'/?'.http_build_query(['rest_route' => '/wc/store/v1/'.$route] + $query);
    }

    /**
     * Cheap availability check: one product, JSON list expected.
     *
     * @return array{available: bool, total: ?int, currency: ?string, status: ?int}
     */
    public function probe(): array
    {
        $response = $this->client->get($this->url('products', ['per_page' => 1]), ['Accept' => 'application/json']);
        $data = $this->decode($response);

        return [
            'available' => $response->successful() && is_array($data) && array_is_list($data),
            'total' => $response->header('x-wp-total') !== null ? (int) $response->header('x-wp-total') : null,
            'currency' => is_array($data) && isset($data[0]['prices']['currency_code']) ? $data[0]['prices']['currency_code'] : null,
            'status' => $response->status,
        ];
    }

    public function pages(ExtractionOptions $options): Generator
    {
        $query = ['per_page' => max(1, min(100, $options->perPage)), 'orderby' => 'id', 'order' => 'asc'];
        if ($options->categoryIds) {
            $query['category'] = implode(',', array_map('intval', $options->categoryIds));
        }

        $page = 1;
        $totalPages = null;
        $emitted = 0;

        while ($totalPages === null || $page <= $totalPages) {
            $options->checkpoint();
            $response = $this->client->get($this->url('products', $query + ['page' => $page]), ['Accept' => 'application/json']);
            if (! $response->successful()) {
                // WordPress answers 400 rest_post_invalid_page_number past the last page.
                if ($page > 1 && $response->status === 400) {
                    return;
                }
                throw new FetchFailedException("Store API page {$page} answered {$response->status}");
            }
            $items = $this->decode($response);
            if (! is_array($items) || ! array_is_list($items)) {
                throw new FetchFailedException("Store API page {$page} did not return a product list");
            }
            if ($items === []) {
                return;
            }
            $totalPages ??= $response->header('x-wp-totalpages') !== null ? max(1, (int) $response->header('x-wp-totalpages')) : PHP_INT_MAX;

            // Hand over small groups so progress (and saved results) advance while variations load.
            $remaining = array_slice($items, 0, max(0, $options->maxProducts - $emitted));
            foreach (array_chunk($remaining, self::BATCH_SIZE) as $group) {
                $batch = array_map(fn (array $item) => $this->map($item), $group);
                if ($options->includeVariations) {
                    $this->attachVariations($group, $batch, $options);
                }
                $emitted += count($batch);
                yield $batch;
            }

            if ($emitted >= $options->maxProducts) {
                return;
            }
            $page++;
        }
    }

    /**
     * Resolve variations for a group of products: one bulk request (?type=variation&parent=…) for the
     * whole group, then individual requests only for variations the bulk answer did not include
     * (older stores ignore the filter).
     *
     * @param  list<array<string, mixed>>  $items
     * @param  list<ProductData>  $products
     */
    private function attachVariations(array $items, array $products, ExtractionOptions $options): void
    {
        $wanted = [];
        foreach ($items as $i => $item) {
            if ($products[$i]->type === ProductData::TYPE_VARIABLE) {
                foreach ($item['variations'] ?? [] as $ref) {
                    $wanted[(int) $ref['id']] = (int) $item['id'];
                }
            }
        }
        if (! $wanted) {
            return;
        }

        $found = $this->bulkVariations(array_values(array_unique($wanted)), $wanted, $options);

        foreach ($items as $i => $item) {
            if ($products[$i]->type === ProductData::TYPE_VARIABLE) {
                $products[$i]->variations = $this->variations($item, $products[$i], $options, $found);
            }
        }
    }

    /**
     * @param  list<int>  $parentIds
     * @param  array<int, int>  $wanted  variation id => parent id
     * @return array<int, array<string, mixed>> variation id => Store API item
     */
    private function bulkVariations(array $parentIds, array $wanted, ExtractionOptions $options): array
    {
        $found = [];
        $page = 1;
        do {
            $options->checkpoint();
            try {
                $response = $this->client->get($this->url('products', [
                    'type' => 'variation', 'parent' => implode(',', $parentIds), 'per_page' => 100, 'page' => $page,
                ]), ['Accept' => 'application/json']);
            } catch (FetchFailedException) {
                return $found;
            }
            $data = $response->successful() ? $this->decode($response) : null;
            if (! is_array($data) || ! array_is_list($data)) {
                return $found;
            }
            $matched = 0;
            foreach ($data as $row) {
                // Only trust rows that really are variations of the products we asked about.
                $id = (int) ($row['id'] ?? 0);
                if (($row['type'] ?? null) === 'variation' && isset($wanted[$id]) && (int) ($row['parent'] ?? 0) === $wanted[$id]) {
                    $found[$id] = $row;
                    $matched++;
                }
            }
            if ($matched === 0) {
                return $found; // the store ignores the filter: don't page through its whole catalogue
            }
            $pages = (int) ($response->header('x-wp-totalpages') ?? 1);
            $page++;
        } while ($page <= min($pages, 20) && count($found) < count($wanted));

        return $found;
    }

    /**
     * @param  array<int, array<string, mixed>>  $found  variation details already fetched in bulk
     * @return list<ProductData>
     */
    private function variations(array $parentItem, ProductData $parent, ExtractionOptions $options, array $found = []): array
    {
        $out = [];
        foreach ($parentItem['variations'] ?? [] as $ref) {
            $attrs = [];
            foreach ($ref['attributes'] ?? [] as $a) {
                $attrs[(string) Html::decode($a['name'] ?? '')] = (string) Html::decode((string) ($a['value'] ?? ''));
            }
            $item = $found[(int) $ref['id']] ?? null;
            if ($item === null) {
                $options->checkpoint();
                try {
                    $response = $this->client->get($this->url('products/'.(int) $ref['id']), ['Accept' => 'application/json']);
                    $item = $response->successful() ? $this->decode($response) : null;
                } catch (FetchException $e) {
                    if (! $e instanceof FetchFailedException) {
                        throw $e;
                    }
                    $item = null;
                }
            }
            if (! is_array($item)) {
                $out[] = new ProductData(
                    externalId: (string) $ref['id'], type: ProductData::TYPE_VARIATION,
                    name: $this->variationName($parent->name, $attrs), permalink: $parent->permalink,
                    method: $this->form, parentExternalId: $parent->externalId, currency: $parent->currency,
                    variationAttributes: $attrs, warnings: ['variation_details_unavailable'],
                );

                continue;
            }
            $out[] = $this->map($item, $parent, $attrs);
        }

        return $out;
    }

    /**
     * Map one Store API product object.
     *
     * @param  array<string, string>  $variationAttributes
     */
    public function map(array $item, ?ProductData $parent = null, array $variationAttributes = []): ProductData
    {
        $prices = $item['prices'] ?? [];
        $minor = (int) ($prices['currency_minor_unit'] ?? 2);
        $regular = Price::fromMinor($prices['regular_price'] ?? null, $minor);
        $price = Price::fromMinor($prices['price'] ?? null, $minor);
        $sale = Price::fromMinor($prices['sale_price'] ?? null, $minor);
        // The Store API repeats the regular price in sale_price when nothing is on sale.
        $onSale = (bool) ($item['on_sale'] ?? false) && Price::lessThan($sale, $regular);

        $type = $parent ? ProductData::TYPE_VARIATION : match ($item['type'] ?? 'simple') {
            'variable' => ProductData::TYPE_VARIABLE,
            default => (string) ($item['type'] ?? ProductData::TYPE_SIMPLE),
        };

        $name = (string) Html::decode($item['name'] ?? '');
        if ($parent) {
            $name = $this->variationName($parent->name, $variationAttributes);
        }

        $description = $item['description'] ?? null;

        return new ProductData(
            externalId: (string) $item['id'],
            type: $type,
            name: $name,
            permalink: (string) ($item['permalink'] ?? ''),
            method: $this->form,
            parentExternalId: $parent?->externalId,
            slug: $parent ? null : ($item['slug'] ?? null),
            sku: Html::decode($item['sku'] ?? null),
            // A variable parent's "regular price" is just its cheapest variation; keep the range instead.
            regularPrice: $type === ProductData::TYPE_VARIABLE ? null : $regular,
            salePrice: $onSale && $type !== ProductData::TYPE_VARIABLE ? $sale : null,
            price: $price,
            priceMin: Price::fromMinor($prices['price_range']['min_amount'] ?? null, $minor),
            priceMax: Price::fromMinor($prices['price_range']['max_amount'] ?? null, $minor),
            currency: $prices['currency_code'] ?? $parent?->currency,
            stockStatus: $this->stockStatus($item),
            stockQuantity: $this->stockQuantity($item),
            categories: $parent ? [] : $this->terms($item['categories'] ?? []),
            tags: $parent ? [] : $this->terms($item['tags'] ?? []),
            brands: $parent ? [] : array_values(array_filter(array_map(fn ($b) => Html::decode($b['name'] ?? null), $item['brands'] ?? []))),
            attributes: $parent ? [] : $this->attributes($item['attributes'] ?? []),
            variationAttributes: $variationAttributes,
            images: $this->images($item['images'] ?? []),
            shortDescriptionHtml: $parent ? null : Html::sanitize($item['short_description'] ?? null),
            descriptionHtml: Html::sanitize($description),
            descriptionText: Html::toText($description),
            ratingAverage: isset($item['average_rating']) && is_numeric($item['average_rating']) ? (float) $item['average_rating'] : null,
            reviewCount: isset($item['review_count']) ? (int) $item['review_count'] : null,
        );
    }

    private function stockStatus(array $item): string
    {
        if (! empty($item['is_on_backorder'])) {
            return ProductData::STOCK_BACKORDER;
        }
        if (array_key_exists('is_in_stock', $item)) {
            return $item['is_in_stock'] ? ProductData::STOCK_IN : ProductData::STOCK_OUT;
        }

        return ProductData::STOCK_UNKNOWN;
    }

    private function stockQuantity(array $item): ?int
    {
        if (isset($item['low_stock_remaining']) && is_numeric($item['low_stock_remaining'])) {
            return (int) $item['low_stock_remaining'];
        }
        $text = (string) ($item['stock_availability']['text'] ?? '');
        if (preg_match('/(\d[\d,.]*)\s+in stock/i', $text, $m)) {
            return (int) preg_replace('/\D/', '', $m[1]);
        }

        return null;
    }

    /** @return list<array{name: string, slug: ?string}> */
    private function terms(array $terms): array
    {
        $out = [];
        foreach ($terms as $t) {
            $name = Html::decode($t['name'] ?? null);
            if ($name !== null) {
                $out[] = ['name' => $name, 'slug' => $t['slug'] ?? null];
            }
        }

        return $out;
    }

    /** @return list<array{name: string, options: list<string>, variation: bool}> */
    private function attributes(array $attributes): array
    {
        $out = [];
        foreach ($attributes as $a) {
            $name = Html::decode($a['name'] ?? null);
            if ($name === null) {
                continue;
            }
            $out[] = [
                'name' => $name,
                'options' => array_values(array_filter(array_map(fn ($t) => Html::decode($t['name'] ?? null), $a['terms'] ?? []))),
                'variation' => (bool) ($a['has_variations'] ?? false),
            ];
        }

        return $out;
    }

    /** @return list<array{src: string, alt: ?string}> */
    private function images(array $images): array
    {
        $out = [];
        foreach ($images as $img) {
            if (! empty($img['src'])) {
                $out[] = ['src' => (string) $img['src'], 'alt' => Html::decode($img['alt'] ?? null)];
            }
        }

        return $out;
    }

    /** @param array<string, string> $attrs */
    private function variationName(string $parentName, array $attrs): string
    {
        $values = array_values(array_filter($attrs, fn ($v) => $v !== ''));

        return $values ? $parentName.' – '.implode(', ', $values) : $parentName;
    }

    private function decode(FetchResponse $response): mixed
    {
        $body = ltrim($response->body, "\xEF\xBB\xBF \t\r\n");

        return json_decode($body, true, 512, JSON_BIGINT_AS_STRING);
    }
}
