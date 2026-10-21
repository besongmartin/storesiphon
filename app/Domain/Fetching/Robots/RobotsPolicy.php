<?php

namespace App\Domain\Fetching\Robots;

use App\Domain\Fetching\FetchResponse;
use App\Domain\Fetching\SafeUrl;
use Illuminate\Contracts\Cache\Repository;

/**
 * Fetches, interprets and caches robots.txt per origin.
 *
 * Status handling (RFC 9309 §2.3.1): 2xx → parse; 4xx (incl. 404) → no restrictions;
 * 5xx or unreachable → treat as complete disallow (conservative: we never crawl a site whose
 * robots.txt we could not read because it was failing). Failures are cached only briefly so a
 * transient outage does not block a store for a whole day.
 */
final class RobotsPolicy
{
    public function __construct(
        private readonly Repository $cache,
        private readonly string $agent,
        private readonly int $ttlSeconds = 86400,
        private readonly int $failureTtlSeconds = 600,
    ) {}

    /**
     * @param  callable(SafeUrl): ?FetchResponse  $fetch  fetches the robots.txt URL; null when unreachable
     */
    public function for(SafeUrl $url, callable $fetch): RobotsTxt
    {
        $key = 'robots:'.$url->origin();
        $cached = $this->cache->get($key);
        if ($cached instanceof RobotsTxt) {
            return $cached;
        }

        $robotsUrl = new SafeUrl($url->origin().'/robots.txt', $url->scheme, $url->host, $url->port, '/robots.txt');
        $response = $fetch($robotsUrl);

        if ($response === null || $response->status >= 500) {
            $robots = RobotsTxt::disallowAll(unreachable: true);
            $this->cache->put($key, $robots, $this->failureTtlSeconds);

            return $robots;
        }

        $robots = ($response->status >= 200 && $response->status < 300)
            ? RobotsTxt::parse($response->body, $this->agent)
            : RobotsTxt::allowAll(); // 3xx exhausted or 4xx: no restrictions

        $this->cache->put($key, $robots, $this->ttlSeconds);

        return $robots;
    }

    public function forget(SafeUrl $url): void
    {
        $this->cache->forget('robots:'.$url->origin());
    }
}
