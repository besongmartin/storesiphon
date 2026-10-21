<?php

namespace App\Domain\Extraction\Data;

/**
 * One normalized product or variation, independent of how it was extracted.
 *
 * Prices are decimal strings ("1234.50") in the store currency; salePrice is only set when the product
 * is genuinely on sale (sale < regular). Variation rows carry their parent's externalId and their own
 * attribute values in $variationAttributes.
 */
final class ProductData
{
    public const TYPE_SIMPLE = 'simple';

    public const TYPE_VARIABLE = 'variable';

    public const TYPE_VARIATION = 'variation';

    public const STOCK_IN = 'instock';

    public const STOCK_OUT = 'outofstock';

    public const STOCK_BACKORDER = 'onbackorder';

    public const STOCK_UNKNOWN = 'unknown';

    /**
     * @param  list<array{name: string, slug: ?string}>  $categories
     * @param  list<array{name: string, slug: ?string}>  $tags
     * @param  list<string>  $brands
     * @param  list<array{name: string, options: list<string>, variation: bool}>  $attributes
     * @param  array<string, string>  $variationAttributes  attribute name => value (variations only)
     * @param  list<array{src: string, alt: ?string}>  $images
     * @param  list<self>  $variations
     * @param  list<string>  $warnings
     */
    public function __construct(
        public readonly string $externalId,
        public readonly string $type,
        public readonly string $name,
        public readonly string $permalink,
        public readonly string $method,
        public readonly ?string $parentExternalId = null,
        public readonly ?string $slug = null,
        public readonly ?string $sku = null,
        public readonly ?string $regularPrice = null,
        public readonly ?string $salePrice = null,
        public readonly ?string $price = null,
        public readonly ?string $priceMin = null,
        public readonly ?string $priceMax = null,
        public readonly ?string $currency = null,
        public readonly string $stockStatus = self::STOCK_UNKNOWN,
        public readonly ?int $stockQuantity = null,
        public readonly array $categories = [],
        public readonly array $tags = [],
        public readonly array $brands = [],
        public readonly array $attributes = [],
        public readonly array $variationAttributes = [],
        public readonly array $images = [],
        public readonly ?string $shortDescriptionHtml = null,
        public readonly ?string $descriptionHtml = null,
        public readonly ?string $descriptionText = null,
        public readonly ?float $ratingAverage = null,
        public readonly ?int $reviewCount = null,
        public array $variations = [],
        public readonly array $warnings = [],
    ) {}

    public function isOnSale(): bool
    {
        return $this->salePrice !== null;
    }

    /** Stable fingerprint of the extracted values (used to skip unchanged rows on re-runs). */
    public function contentHash(): string
    {
        $data = $this->toArray();
        unset($data['method'], $data['warnings'], $data['variations']);

        return hash('sha256', json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $out = get_object_vars($this);
        $out['variations'] = array_map(fn (self $v) => $v->toArray(), $this->variations);

        return $out;
    }
}
