<?php

namespace App\Domain\Fetching;

use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Sleep;

/**
 * Politeness per store host, shared by all workers through the cache:
 *  - requests to one host start at least $interval seconds apart (max of our minimum and the
 *    site's robots.txt Crawl-delay);
 *  - at most $maxConcurrency requests to one host are in flight at the same time.
 *
 * Uses Illuminate\Support\Sleep so tests can fake time deterministically.
 */
final class HostThrottle
{
    public function __construct(
        private readonly Repository $cache,
        private readonly float $minInterval = 1.0,
        private readonly int $maxConcurrency = 2,
        private readonly int $slotTtlSeconds = 120,
    ) {}

    /**
     * Wait for this host's turn, run $callback, and release the concurrency slot.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public function run(string $host, float $crawlDelay, callable $callback): mixed
    {
        $lock = $this->acquireSlot($host);

        try {
            $this->waitForTurn($host, max($this->minInterval, $crawlDelay));

            return $callback();
        } finally {
            $lock?->release();
        }
    }

    private function waitForTurn(string $host, float $interval): void
    {
        $key = 'fetch:next-at:'.$host;

        while (true) {
            $now = $this->now();
            $wait = null;

            $this->atomically('fetch:turn:'.$host, function () use ($key, $now, $interval, &$wait) {
                $nextAt = (float) $this->cache->get($key, 0);
                if ($now >= $nextAt) {
                    $this->cache->put($key, $now + $interval, (int) ceil($interval) + 60);
                    $wait = 0.0;
                } else {
                    $wait = $nextAt - $now;
                }
            });

            if ($wait <= 0.0) {
                return;
            }

            Sleep::for((int) ceil($wait * 1000))->milliseconds();
        }
    }

    private function acquireSlot(string $host): ?Lock
    {
        $store = $this->cache->getStore();
        if (! $store instanceof LockProvider) {
            return null;
        }

        while (true) {
            for ($i = 0; $i < $this->maxConcurrency; $i++) {
                $lock = $store->lock("fetch:slot:{$host}:{$i}", $this->slotTtlSeconds);
                if ($lock->get()) {
                    return $lock;
                }
            }
            Sleep::for(250)->milliseconds();
        }
    }

    private function atomically(string $name, callable $fn): void
    {
        $store = $this->cache->getStore();
        if ($store instanceof LockProvider) {
            $store->lock($name, 5)->block(5, $fn);

            return;
        }
        $fn();
    }

    private function now(): float
    {
        return Carbon::now()->getPreciseTimestamp(3) / 1000;
    }
}
