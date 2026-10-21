<?php

namespace App\Domain\Extraction\Parsing;

use App\Domain\Extraction\Data\ProductData;
use App\Domain\Extraction\Support\Html;
use App\Domain\Extraction\Support\Price;
use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Extracts a product from a public WooCommerce product page.
 *
 * Sources, in order of trust: WooCommerce markup (title, SKU, terms, variations JSON, price del/ins)
 * then schema.org JSON-LD (price, currency, availability, images, brand, rating), including @graph
 * documents written by SEO plugins.
 */
final class ProductPageParser
{
    private DOMXPath $xpath;

    /** @var array<string, mixed>|null */
    private ?array $ld = null;

    public function __construct(private readonly string $html, private readonly string $url) {}

    public static function looksLikeProductPage(string $html): bool
    {
        return str_contains($html, 'single-product') || str_contains($html, 'product_title')
            || (bool) preg_match('/"@type"\s*:\s*"(Product|ProductGroup)"/', $html);
    }

    public function parse(string $method = 'sitemap'): ?ProductData
    {
        $doc = new DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8">'.$this->html, LIBXML_NONET | LIBXML_NOWARNING | LIBXML_NOERROR);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $this->xpath = new DOMXPath($doc);
        $this->ld = $this->findJsonLdProduct();

        $name = Html::decode($this->text('//h1[contains(concat(" ", normalize-space(@class), " "), " product_title ")]'))
            ?? $this->ldName();
        if ($name === null) {
            return null;
        }

        $warnings = [];
        $variationsJson = $this->variationsJson($warnings);
        $isVariable = $variationsJson !== null || $this->exists('//form[contains(@class,"variations_form")]');

        $offers = $this->offers();
        $currency = $offers['currency'];
        [$regular, $sale] = $this->htmlPrices();
        $price = $sale ?? $regular ?? $offers['price'] ?? $offers['low'];
        $regular ??= $offers['price'];

        $externalId = $this->productId() ?? substr(sha1($this->url), 0, 16);
        $descriptionHtml = $this->innerHtml('//*[@id="tab-description"]') ?? ($this->ld['description'] ?? null);

        $product = new ProductData(
            externalId: $externalId,
            type: $isVariable ? ProductData::TYPE_VARIABLE : ProductData::TYPE_SIMPLE,
            name: $name,
            permalink: $this->canonical() ?? $this->url,
            method: $method,
            slug: $this->slugFromUrl(),
            sku: Html::decode($this->text('//*[contains(concat(" ", normalize-space(@class), " "), " sku ")]')) ?? Html::decode($this->ld['sku'] ?? null),
            regularPrice: $isVariable ? null : $regular,
            salePrice: (! $isVariable && $sale !== null && Price::lessThan($sale, $regular)) ? $sale : null,
            price: $isVariable ? $offers['low'] : $price,
            priceMin: $offers['low'],
            priceMax: $offers['high'],
            currency: $currency,
            stockStatus: $this->stockStatus($offers['availability']),
            stockQuantity: $this->stockQuantity(),
            categories: $this->termLinks('posted_in'),
            tags: $this->termLinks('tagged_as'),
            brands: $this->brands(),
            attributes: $this->attributes($variationsJson),
            images: $this->images(),
            shortDescriptionHtml: Html::sanitize($this->innerHtml('//*[contains(concat(" ", normalize-space(@class), " "), " woocommerce-product-details__short-description ")]')),
            descriptionHtml: Html::sanitize($descriptionHtml),
            descriptionText: Html::toText($descriptionHtml),
            ratingAverage: isset($this->ld['aggregateRating']['ratingValue']) ? (float) $this->ld['aggregateRating']['ratingValue'] : null,
            reviewCount: isset($this->ld['aggregateRating']['reviewCount']) ? (int) $this->ld['aggregateRating']['reviewCount'] : null,
            warnings: $warnings,
        );

        if ($variationsJson) {
            $product->variations = $this->variations($variationsJson, $product, $method);
        }

        return $product;
    }

    /** @return list<ProductData> */
    private function variations(array $rows, ProductData $parent, string $method): array
    {
        $out = [];
        foreach ($rows as $row) {
            if (! is_array($row) || ! isset($row['variation_id'])) {
                continue;
            }
            $attrs = [];
            foreach ((array) ($row['attributes'] ?? []) as $key => $value) {
                $attrs[$this->attributeLabel((string) $key)] = (string) Html::decode((string) $value) ?: 'Any';
            }
            $regular = $this->scaled(Price::parse($row['display_regular_price'] ?? null), $parent);
            $priceNow = $this->scaled(Price::parse($row['display_price'] ?? null), $parent);
            $values = array_values(array_filter($attrs, fn ($v) => $v !== ''));
            $image = $row['image']['full_src'] ?? $row['image']['src'] ?? $row['image']['url'] ?? null;

            $out[] = new ProductData(
                externalId: (string) $row['variation_id'],
                type: ProductData::TYPE_VARIATION,
                name: $values ? $parent->name.' – '.implode(', ', $values) : $parent->name,
                permalink: $parent->permalink,
                method: $method,
                parentExternalId: $parent->externalId,
                sku: Html::decode(isset($row['sku']) ? (string) $row['sku'] : null),
                regularPrice: $regular,
                salePrice: Price::lessThan($priceNow, $regular) ? $priceNow : null,
                price: $priceNow,
                currency: $parent->currency,
                stockStatus: isset($row['is_in_stock']) ? ($row['is_in_stock'] ? ProductData::STOCK_IN : ProductData::STOCK_OUT) : ProductData::STOCK_UNKNOWN,
                stockQuantity: isset($row['max_qty']) && is_numeric($row['max_qty']) && (int) $row['max_qty'] > 0 ? (int) $row['max_qty'] : null,
                variationAttributes: $attrs,
                images: $image ? [['src' => (string) $image, 'alt' => Html::decode($row['image']['alt'] ?? null)]] : [],
                descriptionHtml: Html::sanitize($row['variation_description'] ?? null),
                descriptionText: Html::toText($row['variation_description'] ?? null),
            );
        }

        return $out;
    }

    /** Give a bare number the store's decimal precision, taken from the page's own prices (default 2). */
    private function scaled(?string $value, ProductData $parent): ?string
    {
        if ($value === null) {
            return null;
        }
        $reference = $parent->priceMin ?? $parent->price ?? $parent->priceMax;
        $scale = $reference !== null && str_contains($reference, '.') ? strlen(substr(strrchr($reference, '.'), 1)) : 2;

        return bcadd($value, '0', $scale);
    }

    /** @param list<string> $warnings */
    private function variationsJson(array &$warnings): ?array
    {
        $form = $this->first('//form[contains(@class,"variations_form")]');
        if (! $form instanceof DOMElement || ! $form->hasAttribute('data-product_variations')) {
            return null;
        }
        $raw = $form->getAttribute('data-product_variations');
        if (trim($raw) === 'false') {
            // WooCommerce loads variations by AJAX when a product has many; not available from the page.
            $warnings[] = 'variations_loaded_by_ajax';

            return null;
        }
        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    /** "attribute_pa_size" → label from the variations form, else "Size". */
    private function attributeLabel(string $key): string
    {
        $slug = preg_replace('/^attribute_(pa_)?/', '', $key);
        $label = $this->text('//form[contains(@class,"variations_form")]//label[@for="'.htmlspecialchars($slug, ENT_QUOTES).'" or @for="pa_'.htmlspecialchars($slug, ENT_QUOTES).'"]');

        return Html::decode($label) ?? ucwords(str_replace(['-', '_'], ' ', $slug));
    }

    /** @return array{price: ?string, low: ?string, high: ?string, currency: ?string, availability: ?string} */
    private function offers(): array
    {
        $out = ['price' => null, 'low' => null, 'high' => null, 'currency' => null, 'availability' => null];
        $offers = $this->ld['offers'] ?? null;
        if (! is_array($offers)) {
            return $out;
        }
        $list = array_is_list($offers) ? $offers : [$offers];
        $prices = [];
        foreach ($list as $offer) {
            if (! is_array($offer)) {
                continue;
            }
            $out['currency'] ??= $offer['priceCurrency'] ?? $offer['priceSpecification']['priceCurrency'] ?? null;
            $out['availability'] ??= is_string($offer['availability'] ?? null) ? $offer['availability'] : null;
            foreach (['price', 'lowPrice', 'highPrice'] as $field) {
                $value = $offer[$field] ?? ($field === 'price' ? ($offer['priceSpecification']['price'] ?? null) : null);
                if (($p = Price::parse(is_scalar($value) ? $value : null)) !== null) {
                    $prices[$field][] = $p;
                }
            }
        }
        $all = array_merge(...array_values($prices ?: [[]]));
        if ($all) {
            usort($all, fn ($a, $b) => bccomp($a, $b, 8));
            $out['low'] = $prices['lowPrice'][0] ?? $all[0];
            $out['high'] = $prices['highPrice'][0] ?? end($all);
            $out['price'] = $prices['price'][0] ?? null;
        }

        return $out;
    }

    /** Regular / sale price from the WooCommerce price block (del = regular, ins = sale). @return array{0: ?string, 1: ?string} */
    private function htmlPrices(): array
    {
        $block = '(//*[contains(concat(" ", normalize-space(@class), " "), " summary ")]//p[contains(concat(" ", normalize-space(@class), " "), " price ")])[1]';
        $del = Price::parse($this->text($block.'//del//*[contains(@class,"amount")]'));
        $ins = Price::parse($this->text($block.'//ins//*[contains(@class,"amount")]'));
        if ($del !== null && $ins !== null) {
            return [$del, $ins];
        }
        $amounts = $this->xpath->query($block.'//*[contains(@class,"woocommerce-Price-amount")]');
        if ($amounts && $amounts->length === 1) {
            return [Price::parse($amounts->item(0)->textContent), null];
        }

        return [null, null];
    }

    private function stockStatus(?string $availability): string
    {
        $class = $this->first('//*[contains(concat(" ", normalize-space(@class), " "), " stock ")]');
        $classes = $class instanceof DOMElement ? ' '.$class->getAttribute('class').' ' : '';
        if (str_contains($classes, ' out-of-stock ')) {
            return ProductData::STOCK_OUT;
        }
        if (str_contains($classes, ' available-on-backorder ')) {
            return ProductData::STOCK_BACKORDER;
        }
        if (str_contains($classes, ' in-stock ')) {
            return ProductData::STOCK_IN;
        }

        return match (true) {
            $availability === null => ProductData::STOCK_UNKNOWN,
            str_ends_with($availability, 'OutOfStock') || str_ends_with($availability, 'SoldOut') => ProductData::STOCK_OUT,
            str_ends_with($availability, 'BackOrder') || str_ends_with($availability, 'PreOrder') => ProductData::STOCK_BACKORDER,
            str_ends_with($availability, 'InStock') || str_ends_with($availability, 'LimitedAvailability') => ProductData::STOCK_IN,
            default => ProductData::STOCK_UNKNOWN,
        };
    }

    private function stockQuantity(): ?int
    {
        $text = $this->text('(//*[contains(concat(" ", normalize-space(@class), " "), " summary ")]//*[contains(concat(" ", normalize-space(@class), " "), " stock ")])[1]');
        if ($text !== null && preg_match('/(\d[\d,.]*)\s+in stock/i', $text, $m)) {
            return (int) preg_replace('/\D/', '', $m[1]);
        }

        return null;
    }

    /** @return list<array{name: string, slug: ?string}> */
    private function termLinks(string $class): array
    {
        $out = [];
        $nodes = $this->xpath->query('(//*[contains(concat(" ", normalize-space(@class), " "), " '.$class.' ")])[1]//a');
        foreach ($nodes ?: [] as $a) {
            $name = Html::decode($a->textContent);
            if ($name === null) {
                continue;
            }
            $href = $a instanceof DOMElement ? $a->getAttribute('href') : '';
            $slug = $href ? basename(rtrim((string) parse_url($href, PHP_URL_PATH), '/')) : null;
            $out[] = ['name' => $name, 'slug' => $slug ?: null];
        }

        if (! $out && $class === 'posted_in' && is_string($this->ld['category'] ?? null)) {
            foreach (explode('>', (string) Html::decode($this->ld['category'])) as $part) {
                if (($name = trim($part)) !== '') {
                    $out[] = ['name' => $name, 'slug' => null];
                }
            }
        }

        return $out;
    }

    /** @return list<string> */
    private function brands(): array
    {
        $brand = $this->ld['brand'] ?? null;
        $names = [];
        foreach (is_array($brand) && array_is_list($brand) ? $brand : [$brand] as $b) {
            $name = is_array($b) ? ($b['name'] ?? null) : $b;
            if (is_string($name) && ($name = Html::decode($name)) !== null) {
                $names[] = $name;
            }
        }

        return $names;
    }

    /** @return list<array{name: string, options: list<string>, variation: bool}> */
    private function attributes(?array $variations): array
    {
        $out = [];
        $rows = $this->xpath->query('//table[contains(@class,"woocommerce-product-attributes")]//tr');
        foreach ($rows ?: [] as $row) {
            $label = Html::decode($this->xpath->query('.//th', $row)?->item(0)?->textContent);
            $value = Html::decode($this->xpath->query('.//td', $row)?->item(0)?->textContent);
            if ($label && $value && ! in_array(strtolower($label), ['weight', 'dimensions'], true)) {
                $out[$label] = ['name' => $label, 'options' => array_map('trim', explode(',', $value)), 'variation' => false];
            }
        }
        // Variation attributes take their options from the variations themselves (exact values each
        // variation row carries), replacing the display text of the attributes table.
        $fromVariations = [];
        foreach ($variations ?? [] as $row) {
            foreach ((array) ($row['attributes'] ?? []) as $key => $value) {
                $label = $this->attributeLabel((string) $key);
                $v = Html::decode((string) $value);
                if ($v !== null && ! in_array($v, $fromVariations[$label] ?? [], true)) {
                    $fromVariations[$label][] = $v;
                }
            }
        }
        foreach ($fromVariations as $label => $options) {
            $out[$label] = ['name' => $label, 'options' => $options, 'variation' => true];
        }

        return array_values($out);
    }

    /** @return list<array{src: string, alt: ?string}> */
    private function images(): array
    {
        $out = [];
        $nodes = $this->xpath->query('//*[contains(@class,"woocommerce-product-gallery__image")]');
        foreach ($nodes ?: [] as $node) {
            $a = $this->xpath->query('.//a[@href]', $node)?->item(0);
            $img = $this->xpath->query('.//img', $node)?->item(0);
            $src = $img instanceof DOMElement ? ($img->getAttribute('data-large_image') ?: null) : null;
            $src ??= $a instanceof DOMElement ? $a->getAttribute('href') : null;
            $src ??= $img instanceof DOMElement ? $img->getAttribute('src') : null;
            if ($src && preg_match('#^https?://#i', $src)) {
                $out[$src] = ['src' => $src, 'alt' => $img instanceof DOMElement ? Html::decode($img->getAttribute('alt')) : null];
            }
        }
        if (! $out) {
            $ldImages = $this->ld['image'] ?? [];
            foreach (is_array($ldImages) && array_is_list($ldImages) ? $ldImages : [$ldImages] as $img) {
                $src = is_array($img) ? ($img['url'] ?? $img['contentUrl'] ?? null) : $img;
                if (is_string($src) && preg_match('#^https?://#i', $src)) {
                    $out[$src] = ['src' => $src, 'alt' => null];
                }
            }
        }

        return array_values($out);
    }

    private function productId(): ?string
    {
        foreach (['//form[contains(@class,"variations_form")]/@data-product_id', '//button[@name="add-to-cart"]/@value', '//input[@name="add-to-cart"]/@value', '//*[@id[starts-with(., "product-")]]/@id'] as $q) {
            $v = $this->text($q);
            if ($v !== null && preg_match('/(\d+)$/', $v, $m)) {
                return $m[1];
            }
        }

        return null;
    }

    private function canonical(): ?string
    {
        $href = $this->text('//link[@rel="canonical"]/@href');

        return $href && preg_match('#^https?://#i', $href) ? $href : null;
    }

    private function slugFromUrl(): ?string
    {
        $path = trim((string) parse_url($this->canonical() ?? $this->url, PHP_URL_PATH), '/');

        return $path === '' ? null : basename($path);
    }

    private function ldName(): ?string
    {
        return Html::decode(is_string($this->ld['name'] ?? null) ? $this->ld['name'] : null);
    }

    /** @return array<string, mixed>|null */
    private function findJsonLdProduct(): ?array
    {
        foreach ($this->xpath->query('//script[@type="application/ld+json"]') ?: [] as $script) {
            $data = json_decode(trim($script->textContent), true);
            if (! is_array($data)) {
                continue;
            }
            $nodes = isset($data['@graph']) && is_array($data['@graph']) ? $data['@graph'] : (array_is_list($data) ? $data : [$data]);
            foreach ($nodes as $node) {
                $types = (array) ($node['@type'] ?? []);
                if (array_intersect($types, ['Product', 'ProductGroup'])) {
                    return $node;
                }
            }
        }

        return null;
    }

    private function text(string $query): ?string
    {
        $node = $this->xpath->query($query)?->item(0);

        return $node ? trim($node->textContent) : null;
    }

    private function first(string $query): mixed
    {
        return $this->xpath->query($query)?->item(0);
    }

    private function exists(string $query): bool
    {
        return (bool) $this->xpath->query($query)?->length;
    }

    private function innerHtml(string $query): ?string
    {
        $node = $this->first($query);
        if (! $node) {
            return null;
        }
        $html = '';
        foreach ($node->childNodes as $child) {
            $html .= $node->ownerDocument->saveHTML($child);
        }

        return trim($html) === '' ? null : $html;
    }
}
