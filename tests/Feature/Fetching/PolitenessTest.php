<?php

use App\Domain\Fetching\HostThrottle;
use App\Domain\Fetching\Robots\RobotsPolicy;
use App\Domain\Fetching\SafeHttpClient;
use App\Domain\Fetching\UrlGuard;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Support\FakeDnsResolver;

beforeEach(function () {
    Carbon::setTestNow('2026-10-21 12:00:00');
    Sleep::fake(syncWithCarbon: true);
});

function politeClient(FakeDnsResolver $dns): SafeHttpClient
{
    $cache = Cache::store('array');

    return new SafeHttpClient(
        app(HttpFactory::class), $dns, new UrlGuard,
        new HostThrottle($cache, 1.0, 2), new RobotsPolicy($cache, 'StoreSiphonBot'),
        config('fetching'),
    );
}

/** Seconds between consecutive requests to paths that match $suffix. */
function gapsBetween(array $times): array
{
    $gaps = [];
    for ($i = 1; $i < count($times); $i++) {
        $gaps[] = $times[$i] - $times[$i - 1];
    }

    return $gaps;
}

it('honours robots.txt Crawl-delay within ±10 %', function () {
    $times = [];
    Http::fake(function (Request $request) use (&$times) {
        if (str_ends_with($request->url(), '/robots.txt')) {
            return Http::response("User-agent: *\nCrawl-delay: 10\n", 200);
        }
        $times[] = Carbon::now()->getPreciseTimestamp(3) / 1000;

        return Http::response('ok', 200);
    });
    $dns = (new FakeDnsResolver)->set('shop.acme-store.com', ['93.184.216.34']);
    $client = politeClient($dns);

    foreach ([1, 2, 3, 4] as $page) {
        $client->get("https://shop.acme-store.com/shop/page/{$page}");
    }

    expect($times)->toHaveCount(4);
    foreach (gapsBetween($times) as $gap) {
        expect($gap)->toBeGreaterThanOrEqual(9.0)->toBeLessThanOrEqual(11.0);
    }
});

it('spaces requests by the minimum interval when no Crawl-delay is set', function () {
    $times = [];
    Http::fake(function (Request $request) use (&$times) {
        if (str_ends_with($request->url(), '/robots.txt')) {
            return Http::response('', 404);
        }
        $times[] = Carbon::now()->getPreciseTimestamp(3) / 1000;

        return Http::response('ok', 200);
    });
    $client = politeClient((new FakeDnsResolver)->set('shop.acme-store.com', ['93.184.216.34']));

    foreach ([1, 2, 3] as $page) {
        $client->get("https://shop.acme-store.com/p/{$page}");
    }

    foreach (gapsBetween($times) as $gap) {
        expect($gap)->toBeGreaterThanOrEqual(0.9)->toBeLessThanOrEqual(1.1);
    }
});

it('does not make different stores wait for each other', function () {
    Http::fake(fn (Request $r) => str_ends_with($r->url(), '/robots.txt')
        ? Http::response("User-agent: *\nCrawl-delay: 30\n", 200)
        : Http::response('ok', 200));
    $dns = (new FakeDnsResolver)
        ->set('one-store.com', ['93.184.216.34'])
        ->set('two-store.com', ['93.184.216.35']);
    $client = politeClient($dns);

    $start = Carbon::now()->getTimestamp();
    $client->get('https://one-store.com/a');
    $client->get('https://two-store.com/a');

    // Each first page waits only for its own robots.txt request (≥1 s), never for the other store's 30 s delay.
    expect(Carbon::now()->getTimestamp() - $start)->toBeLessThan(5);
});

it('caps an extreme Crawl-delay', function () {
    $times = [];
    Http::fake(function (Request $request) use (&$times) {
        if (str_ends_with($request->url(), '/robots.txt')) {
            return Http::response("User-agent: *\nCrawl-delay: 86400\n", 200);
        }
        $times[] = Carbon::now()->getTimestamp();

        return Http::response('ok', 200);
    });
    $client = politeClient((new FakeDnsResolver)->set('shop.acme-store.com', ['93.184.216.34']));

    $client->get('https://shop.acme-store.com/a');
    $client->get('https://shop.acme-store.com/b');

    expect($times[1] - $times[0])->toBe((int) config('fetching.max_crawl_delay_seconds'));
});
