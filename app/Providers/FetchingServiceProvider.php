<?php

namespace App\Providers;

use App\Domain\Fetching\Dns\DnsResolver;
use App\Domain\Fetching\Dns\SystemDnsResolver;
use App\Domain\Fetching\HostThrottle;
use App\Domain\Fetching\Robots\RobotsPolicy;
use App\Domain\Fetching\SafeHttpClient;
use App\Domain\Fetching\UrlGuard;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;

class FetchingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DnsResolver::class, SystemDnsResolver::class);

        $this->app->singleton(UrlGuard::class, fn () => new UrlGuard(config('fetching.allowed_ports')));

        $this->app->singleton(HostThrottle::class, fn () => new HostThrottle(
            Cache::store(),
            (float) config('fetching.min_interval_seconds'),
            (int) config('fetching.max_concurrency_per_host'),
        ));

        $this->app->singleton(RobotsPolicy::class, fn () => new RobotsPolicy(
            Cache::store(),
            config('fetching.robots_agent'),
            (int) config('fetching.robots_cache_ttl'),
        ));

        $this->app->bind(SafeHttpClient::class, fn (Application $app) => new SafeHttpClient(
            $app->make(HttpFactory::class),
            $app->make(DnsResolver::class),
            $app->make(UrlGuard::class),
            $app->make(HostThrottle::class),
            $app->make(RobotsPolicy::class),
            config('fetching'),
        ));
    }
}
