<?php

namespace App\Domain\Extraction\Preflight;

use App\Domain\Extraction\Sources\SitemapSource;
use App\Domain\Extraction\Sources\StoreApiSource;
use App\Domain\Extraction\Support\Html;
use App\Domain\Fetching\Exceptions\AccessDeniedException;
use App\Domain\Fetching\Exceptions\BlockedUrlException;
use App\Domain\Fetching\Exceptions\ChallengeDetectedException;
use App\Domain\Fetching\Exceptions\FetchException;
use App\Domain\Fetching\Exceptions\RetryLaterException;
use App\Domain\Fetching\Exceptions\RobotsDisallowedException;
use App\Domain\Fetching\SafeHttpClient;
use App\Domain\Fetching\UrlGuard;

/**
 * Checks a store before any extraction is charged or queued.
 *
 * Fallback rules (decision D13 — respect access controls):
 *  - /wp-json disallowed by robots.txt or answering 401/403 → the API is restricted; we do NOT retry the
 *    same API through ?rest_route=, we fall back to public product pages instead.
 *  - ?rest_route= is used only when /wp-json is simply not routed (404 / non-JSON: plain permalinks).
 *  - Homepage or product pages behind 401/403 or a bot challenge → the store is blocked for us.
 */
final class Preflight
{
    public function __construct(
        private readonly SafeHttpClient $client,
        private readonly UrlGuard $guard,
    ) {}

    public function run(string $input): PreflightResult
    {
        try {
            $start = $this->guard->check($this->withScheme($input));
        } catch (BlockedUrlException $e) {
            return new PreflightResult(PreflightResult::BLOCKED, $input, reasonCode: 'invalid_url', message: $e->getMessage());
        }

        // 1. Homepage: follow redirects (http → https, apex → www) to learn the canonical origin.
        try {
            $home = $this->client->get($start->origin().'/', ['Accept' => 'text/html']);
        } catch (AccessDeniedException|ChallengeDetectedException|RobotsDisallowedException|BlockedUrlException $e) {
            return new PreflightResult(PreflightResult::BLOCKED, $start->origin(), reasonCode: $e->reason(), message: $e->getMessage());
        } catch (RetryLaterException $e) {
            return new PreflightResult(PreflightResult::RETRY_LATER, $start->origin(), reasonCode: $e->reason(), message: $e->getMessage(), retryAfterSeconds: $e->retryAfterSeconds);
        } catch (FetchException $e) {
            return new PreflightResult(PreflightResult::UNREACHABLE, $start->origin(), reasonCode: $e->reason(), message: $e->getMessage());
        }

        $origin = $this->guard->check($home->url)->origin();
        $robots = $this->client->robotsFor($this->guard->check($origin.'/'));
        $signals = $this->htmlSignals($home->body);
        $notes = [];
        $methods = [];
        $total = null;
        $currency = null;

        // 2. Store API (pretty URL), then ?rest_route= only when the pretty route is simply missing.
        $api = $this->probeApi(new StoreApiSource($this->client, $origin, StoreApiSource::PRETTY));
        if ($api['state'] === 'ok') {
            $methods[] = StoreApiSource::PRETTY;
        } elseif ($api['state'] === 'missing') {
            $query = $this->probeApi(new StoreApiSource($this->client, $origin, StoreApiSource::QUERY));
            if ($query['state'] === 'ok') {
                $methods[] = StoreApiSource::QUERY;
                $api = $query;
            } else {
                $notes[] = 'The WooCommerce Store API is not available on this store.';
            }
        } else {
            $notes[] = $api['note'];
        }
        if (in_array($api['state'], ['ok'], true)) {
            $signals[] = 'store_api';
            $total = $api['total'];
            $currency = $api['currency'];
        }

        // 3. Sitemaps with product pages.
        $sitemap = new SitemapSource($this->client, $origin, $robots->sitemaps);
        $sample = [];
        try {
            $sample = $sitemap->productUrls(3);
        } catch (AccessDeniedException|ChallengeDetectedException $e) {
            $notes[] = 'Sitemaps are not accessible ('.$e->reason().').';
        }
        if ($sample) {
            $methods[] = SitemapSource::METHOD;
            if (array_filter($sample, fn ($u) => str_contains($u, '/product/'))) {
                $signals[] = 'product_sitemap';
            }
        }

        $signals = array_values(array_unique($signals));
        $common = [
            'origin' => $origin,
            'signals' => $signals,
            'storeName' => $this->storeName($home->body),
            'crawlDelay' => $robots->crawlDelay,
            'sitemaps' => $robots->sitemaps,
            'notes' => $notes,
        ];

        if (! $signals) {
            return new PreflightResult(PreflightResult::NOT_WOOCOMMERCE, ...$common,
                reasonCode: PreflightResult::NOT_WOOCOMMERCE,
                message: 'This site does not appear to run WooCommerce.');
        }
        if (! $methods) {
            return new PreflightResult(PreflightResult::NO_PUBLIC_CATALOGUE, ...$common,
                reasonCode: PreflightResult::NO_PUBLIC_CATALOGUE,
                message: 'This WooCommerce store does not expose its catalogue publicly (API restricted and no product sitemap).');
        }

        return new PreflightResult(PreflightResult::OK, ...$common, methods: $methods, estimatedProducts: $total, currency: $currency);
    }

    /** @return array{state: 'ok'|'missing'|'restricted', total: ?int, currency: ?string, note: ?string} */
    private function probeApi(StoreApiSource $source): array
    {
        try {
            $probe = $source->probe();
        } catch (RobotsDisallowedException) {
            return ['state' => 'restricted', 'total' => null, 'currency' => null, 'note' => 'robots.txt disallows the Store API, so we use public product pages instead.'];
        } catch (AccessDeniedException|ChallengeDetectedException $e) {
            return ['state' => 'restricted', 'total' => null, 'currency' => null, 'note' => 'The store restricts its API ('.$e->reason().'), so we use public product pages instead.'];
        } catch (FetchException) {
            return ['state' => 'missing', 'total' => null, 'currency' => null, 'note' => null];
        }

        if ($probe['available']) {
            return ['state' => 'ok', 'total' => $probe['total'], 'currency' => $probe['currency'], 'note' => null];
        }

        return ['state' => 'missing', 'total' => null, 'currency' => null, 'note' => null];
    }

    /** @return list<string> */
    private function htmlSignals(string $html): array
    {
        $signals = [];
        $checks = [
            'woocommerce_assets' => '/wp-content/plugins/woocommerce/',
            'woocommerce_body_class' => 'woocommerce-',
            'woocommerce_blocks' => 'wc-block-',
            'woocommerce_generator' => 'content="WooCommerce',
        ];
        foreach ($checks as $signal => $needle) {
            if (str_contains($html, $needle)) {
                $signals[] = $signal;
            }
        }

        return $signals;
    }

    private function storeName(string $html): ?string
    {
        if (preg_match('/<meta[^>]+property=["\']og:site_name["\'][^>]+content=["\']([^"\']+)/i', $html, $m)) {
            return Html::decode($m[1]);
        }
        if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $m)) {
            $title = Html::decode($m[1]);

            return $title === null ? null : trim(preg_split('/\s[|–—-]\s/u', $title)[0] ?? $title);
        }

        return null;
    }

    private function withScheme(string $input): string
    {
        $input = trim($input);

        return preg_match('#^[a-z][a-z0-9+.-]*://#i', $input) ? $input : 'https://'.$input;
    }
}
