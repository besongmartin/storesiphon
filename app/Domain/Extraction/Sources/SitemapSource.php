<?php

namespace App\Domain\Extraction\Sources;

use App\Domain\Extraction\Data\ProductData;
use App\Domain\Extraction\ExtractionOptions;
use App\Domain\Extraction\Extractor;
use App\Domain\Extraction\Parsing\ProductPageParser;
use App\Domain\Extraction\Parsing\SitemapParser;
use App\Domain\Fetching\Exceptions\FetchFailedException;
use App\Domain\Fetching\Exceptions\ResponseTooLargeException;
use App\Domain\Fetching\Exceptions\RobotsDisallowedException;
use App\Domain\Fetching\Exceptions\TooManyRedirectsException;
use App\Domain\Fetching\SafeHttpClient;
use Generator;

/**
 * Discovers product URLs from XML sitemaps (robots.txt Sitemap lines, WordPress core, Yoast/Rank Math
 * conventions) and parses each public product page. Slower than the Store API but works when the API
 * is disabled or restricted, and only reads pages robots.txt allows.
 */
final class SitemapSource implements ProductSource
{
    public const METHOD = 'sitemap';

    private const CANDIDATES = ['/wp-sitemap.xml', '/sitemap_index.xml', '/product-sitemap.xml', '/sitemap.xml'];

    private const MAX_SITEMAPS = 20;

    /** @var list<string> */
    public array $skipped = [];

    /** @var array<string, true> lower-cased URL paths already extracted by an earlier method */
    private array $skipPaths = [];

    /** @param list<string> $robotsSitemaps */
    public function __construct(
        private readonly SafeHttpClient $client,
        private readonly string $origin,
        private readonly array $robotsSitemaps = [],
    ) {}

    public function method(): string
    {
        return self::METHOD;
    }

    /** @param list<string> $pathKeys keys produced by Extractor::key() */
    public function skip(array $pathKeys): void
    {
        foreach ($pathKeys as $key) {
            $this->skipPaths[$key] = true;
        }
    }

    /**
     * Product page URLs, discovered lazily (stops once $limit URLs are known).
     *
     * @return list<string>
     */
    public function productUrls(int $limit = PHP_INT_MAX): array
    {
        $queue = array_values(array_unique(array_merge(
            array_filter($this->robotsSitemaps, fn ($u) => $this->sameSite($u)),
            array_map(fn ($p) => $this->origin.$p, self::CANDIDATES),
        )));
        $seen = [];
        $urls = [];
        $fetched = 0;

        while ($queue && $fetched < self::MAX_SITEMAPS && count($urls) < $limit) {
            $sitemap = array_shift($queue);
            if (isset($seen[$sitemap])) {
                continue;
            }
            $seen[$sitemap] = true;

            try {
                $response = $this->client->get($sitemap, ['Accept' => 'application/xml,text/xml;q=0.9,*/*;q=0.5']);
            } catch (FetchFailedException|RobotsDisallowedException|ResponseTooLargeException|TooManyRedirectsException) {
                continue;
            }
            $fetched++;
            if (! $response->successful()) {
                continue;
            }

            $parsed = SitemapParser::parse($response->body);
            if ($parsed['type'] === 'index') {
                // Product sitemaps first; other children only when nothing product-specific exists.
                $children = array_filter($parsed['locs'], fn ($u) => $this->sameSite($u));
                $product = array_values(array_filter($children, fn ($u) => (bool) preg_match('#product#i', $u)));
                $queue = array_merge($product, $queue, $product ? [] : array_values(array_diff($children, $product)));
            } elseif ($parsed['type'] === 'urlset') {
                $isProductMap = (bool) preg_match('#product#i', $sitemap);
                foreach ($parsed['locs'] as $loc) {
                    if (! $this->sameSite($loc)) {
                        continue;
                    }
                    if ($isProductMap || preg_match('#/(product|shop|produkt|produit|producto)/[^/]+/?$#i', (string) parse_url($loc, PHP_URL_PATH))) {
                        $urls[$loc] = true;
                    }
                }
                if ($isProductMap && $urls) {
                    // A dedicated product sitemap is authoritative; keep reading only its siblings.
                    $queue = array_values(array_filter($queue, fn ($u) => (bool) preg_match('#product#i', $u)));
                }
            }
        }

        return array_slice(array_keys($urls), 0, $limit === PHP_INT_MAX ? null : $limit);
    }

    public function pages(ExtractionOptions $options): Generator
    {
        $batch = [];
        $emitted = 0;

        foreach ($this->productUrls(count($this->skipPaths) + $options->maxProducts * 2) as $url) {
            if ($emitted >= $options->maxProducts) {
                break;
            }
            if (isset($this->skipPaths[Extractor::pathKey($url) ?? ''])) {
                continue;
            }
            $product = $this->product($url);
            if ($product === null) {
                continue;
            }
            if (! $options->includeVariations) {
                $product->variations = [];
            }
            $batch[] = $product;
            $emitted++;
            if (count($batch) >= 10) {
                yield $batch;
                $batch = [];
            }
        }

        if ($batch) {
            yield $batch;
        }
    }

    private function product(string $url): ?ProductData
    {
        try {
            $response = $this->client->get($url, ['Accept' => 'text/html,application/xhtml+xml']);
        } catch (FetchFailedException|RobotsDisallowedException|ResponseTooLargeException|TooManyRedirectsException $e) {
            $this->skipped[] = $url.' ('.$e->reason().')';

            return null;
        }
        if (! $response->successful() || ! ProductPageParser::looksLikeProductPage($response->body)) {
            $this->skipped[] = $url.' (not a product page, status '.$response->status.')';

            return null;
        }

        return (new ProductPageParser($response->body, $response->url))->parse(self::METHOD);
    }

    private function sameSite(string $url): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        $origin = strtolower((string) parse_url($this->origin, PHP_URL_HOST));

        return $host === $origin || preg_replace('/^www\./', '', $host) === preg_replace('/^www\./', '', $origin);
    }
}
