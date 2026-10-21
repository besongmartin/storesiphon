<?php

namespace App\Domain\Extraction\Preflight;

/**
 * What we learned about a store before extracting: is it WooCommerce, which methods work, how big
 * it is, and if we cannot proceed, why.
 */
final class PreflightResult
{
    public const OK = 'ok';

    public const NOT_WOOCOMMERCE = 'not_woocommerce';

    public const NO_PUBLIC_CATALOGUE = 'no_public_catalogue';

    public const BLOCKED = 'blocked';

    public const UNREACHABLE = 'unreachable';

    public const RETRY_LATER = 'retry_later';

    /**
     * @param  list<string>  $methods  usable methods in preference order (store_api|rest_route, sitemap)
     * @param  list<string>  $signals  evidence that the site runs WooCommerce
     * @param  list<string>  $sitemaps  sitemap URLs from robots.txt
     * @param  list<string>  $notes  human-readable observations
     */
    public function __construct(
        public readonly string $status,
        public readonly string $origin,
        public readonly array $methods = [],
        public readonly array $signals = [],
        public readonly ?int $estimatedProducts = null,
        public readonly ?string $currency = null,
        public readonly ?string $storeName = null,
        public readonly ?float $crawlDelay = null,
        public readonly array $sitemaps = [],
        public readonly ?string $reasonCode = null,
        public readonly ?string $message = null,
        public readonly array $notes = [],
        public readonly ?int $retryAfterSeconds = null,
    ) {}

    public function canExtract(): bool
    {
        return $this->status === self::OK && $this->methods !== [];
    }

    public function isWooCommerce(): bool
    {
        return $this->signals !== [];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
