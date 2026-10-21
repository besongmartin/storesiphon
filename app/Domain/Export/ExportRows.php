<?php

namespace App\Domain\Export;

use App\Models\ExtractionJob;
use App\Models\Product;
use Generator;

/**
 * Flat, per-variant rows for tabular exports (CSV, XLSX).
 *
 * Simple product → 1 row. Variable product → parent row + 1 row per variation (each with its own SKU,
 * prices, stock and attribute values). Rows are streamed from the database in chunks.
 */
final class ExportRows
{
    public const COLUMNS = [
        'id' => 'ID',
        'parent_id' => 'Parent ID',
        'type' => 'Type',
        'sku' => 'SKU',
        'parent_sku' => 'Parent SKU',
        'name' => 'Name',
        'variation' => 'Variation',
        'regular_price' => 'Regular price',
        'sale_price' => 'Sale price',
        'price' => 'Price',
        'price_min' => 'Min price',
        'price_max' => 'Max price',
        'currency' => 'Currency',
        'on_sale' => 'On sale',
        'stock_status' => 'Stock status',
        'stock_quantity' => 'Stock quantity',
        'categories' => 'Categories',
        'tags' => 'Tags',
        'brands' => 'Brands',
        'attributes' => 'Attributes',
        'image' => 'Image',
        'images' => 'All images',
        'short_description' => 'Short description',
        'description' => 'Description',
        'url' => 'URL',
        'rating' => 'Average rating',
        'review_count' => 'Review count',
        'method' => 'Extraction method',
    ];

    /** Columns that hold numbers (written as numeric cells in XLSX). */
    public const NUMERIC = ['regular_price', 'sale_price', 'price', 'price_min', 'price_max', 'stock_quantity', 'rating', 'review_count'];

    /** @param array{html_descriptions?: bool} $options */
    public function __construct(private readonly array $options = []) {}

    /** @return Generator<int, array<string, string|null>> */
    public function rows(ExtractionJob $job): Generator
    {
        $query = Product::query()
            ->where('extraction_job_id', $job->id)
            ->whereNull('parent_id')
            ->with('variations');

        foreach ($query->lazyById(200) as $product) {
            yield $this->row($product);
            foreach ($product->variations as $variation) {
                yield $this->row($variation, $product);
            }
        }
    }

    /** @return array<string, string|null> */
    public function row(Product $p, ?Product $parent = null): array
    {
        $html = (bool) ($this->options['html_descriptions'] ?? false);
        $images = array_map(fn ($i) => $i['src'], $p->images ?? []);

        return [
            'id' => $p->external_id,
            'parent_id' => $parent?->external_id,
            'type' => $p->type,
            'sku' => $p->sku,
            'parent_sku' => $parent?->sku,
            'name' => $p->name,
            'variation' => $this->pairs($p->variation_attributes ?? []),
            'regular_price' => $this->decimal($p->regular_price),
            'sale_price' => $this->decimal($p->sale_price),
            'price' => $this->decimal($p->price),
            'price_min' => $this->decimal($p->price_min),
            'price_max' => $this->decimal($p->price_max),
            'currency' => $p->currency ?? $parent?->currency,
            'on_sale' => $p->sale_price !== null ? 'yes' : 'no',
            'stock_status' => $p->stock_status,
            'stock_quantity' => $p->stock_quantity !== null ? (string) $p->stock_quantity : null,
            'categories' => $this->names($p->categories ?? $parent?->categories ?? []),
            'tags' => $this->names($p->tags ?? []),
            'brands' => implode(' | ', $p->brands ?? []),
            'attributes' => $this->attributes($p->attributes ?? []),
            'image' => $images[0] ?? ($parent ? (($parent->images[0]['src'] ?? null)) : null),
            'images' => implode(' | ', $images),
            'short_description' => $html ? $p->short_description_html : self::text($p->short_description_html),
            'description' => $html ? $p->description_html : $p->description_text,
            'url' => $p->permalink,
            'rating' => $p->rating_average !== null ? (string) $p->rating_average : null,
            'review_count' => $p->review_count !== null ? (string) $p->review_count : null,
            'method' => $p->method,
        ];
    }

    /** Neutralise spreadsheet formulas in text cells (CSV/XLSX injection). Numeric values are untouched. */
    public static function safeCell(?string $value): ?string
    {
        if ($value === null || $value === '' || is_numeric($value)) {
            return $value;
        }

        return preg_match('/^[=+\-@\t\r]/', $value) ? "'".$value : $value;
    }

    private function decimal(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }
        // DECIMAL(20,6) comes back as "19.990000": trim to at least 2 decimals.
        $trimmed = rtrim(rtrim($value, '0'), '.');
        if (! str_contains($trimmed, '.')) {
            return $trimmed.'.00';
        }

        return strlen(substr(strrchr($trimmed, '.'), 1)) === 1 ? $trimmed.'0' : $trimmed;
    }

    /** @param array<int, array{name: string}> $terms */
    private function names(array $terms): string
    {
        return implode(' | ', array_map(fn ($t) => $t['name'], $terms));
    }

    /** @param array<string, string> $pairs */
    private function pairs(array $pairs): ?string
    {
        if (! $pairs) {
            return null;
        }

        return implode('; ', array_map(fn ($k, $v) => "{$k}: {$v}", array_keys($pairs), $pairs));
    }

    /** @param array<int, array{name: string, options: list<string>}> $attributes */
    private function attributes(array $attributes): string
    {
        return implode(' | ', array_map(fn ($a) => $a['name'].': '.implode(', ', $a['options']), $attributes));
    }

    private static function text(?string $html): ?string
    {
        if ($html === null) {
            return null;
        }
        $text = trim(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        return $text === '' ? null : $text;
    }
}
