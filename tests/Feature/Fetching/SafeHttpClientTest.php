<?php

use App\Domain\Fetching\Exceptions\AccessDeniedException;
use App\Domain\Fetching\Exceptions\BlockedUrlException;
use App\Domain\Fetching\Exceptions\ChallengeDetectedException;
use App\Domain\Fetching\Exceptions\FetchFailedException;
use App\Domain\Fetching\Exceptions\ResponseTooLargeException;
use App\Domain\Fetching\Exceptions\RetryLaterException;
use App\Domain\Fetching\Exceptions\RobotsDisallowedException;
use App\Domain\Fetching\Exceptions\TooManyRedirectsException;
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

const SHOP = 'https://shop.acme-store.com';
const PUBLIC_IP = '93.184.216.34';

beforeEach(function () {
    Carbon::setTestNow('2026-10-21 12:00:00');
    Sleep::fake(syncWithCarbon: true);
    $this->dns = (new FakeDnsResolver)->set('shop.acme-store.com', [PUBLIC_IP]);
});

function safeClient(FakeDnsResolver $dns, array $config = []): SafeHttpClient
{
    $cache = Cache::store('array');

    return new SafeHttpClient(
        app(HttpFactory::class),
        $dns,
        new UrlGuard([80, 443]),
        new HostThrottle($cache, 1.0, 2),
        new RobotsPolicy($cache, 'StoreSiphonBot'),
        array_merge(config('fetching'), ['backoff_base_seconds' => 1.0], $config),
    );
}

function robotsOk(string $body = "User-agent: *\nAllow: /\n"): array
{
    return [SHOP.'/robots.txt' => Http::response($body, 200, ['Content-Type' => 'text/plain'])];
}

it('fetches a public page with our user agent and caches robots.txt', function () {
    Http::fake(robotsOk() + [SHOP.'/*' => Http::response('{"ok":true}', 200, ['Content-Type' => 'application/json'])]);
    $client = safeClient($this->dns);

    $first = $client->get(SHOP.'/wp-json/wc/store/v1/products');
    $client->get(SHOP.'/wp-json/wc/store/v1/products?page=2');

    expect($first->status)->toBe(200)->and($first->json())->toBe(['ok' => true])->and($first->ip)->toBe(PUBLIC_IP);
    Http::assertSent(fn (Request $r) => str_starts_with($r->header('User-Agent')[0], 'StoreSiphonBot/1.0'));
    expect(collect(Http::recorded())->filter(fn ($pair) => str_ends_with($pair[0]->url(), '/robots.txt')))->toHaveCount(1);
});

it('blocks hosts that resolve to non-public addresses', function (string $ip) {
    Http::fake();
    $dns = (new FakeDnsResolver)->set('shop.acme-store.com', [$ip]);

    expect(fn () => safeClient($dns)->get(SHOP.'/'))
        ->toThrow(fn (BlockedUrlException $e) => expect($e->detail)->toBe('private_address'));
    Http::assertNothingSent();
})->with([
    '127.0.0.1', '10.0.0.5', '172.16.3.4', '192.168.1.1', '169.254.169.254', '100.64.0.1', '0.0.0.0',
    '::1', 'fd00::1', 'fe80::1', '::ffff:127.0.0.1', '64:ff9b::a9fe:a9fe', '2002:7f00:1::1',
]);

it('blocks a host when any DNS answer is private', function () {
    Http::fake();
    $dns = (new FakeDnsResolver)->set('shop.acme-store.com', [PUBLIC_IP, '10.0.0.7']);

    expect(fn () => safeClient($dns)->get(SHOP.'/'))->toThrow(BlockedUrlException::class);
    Http::assertNothingSent();
});

it('re-resolves on every hop, defeating DNS rebinding', function () {
    Http::fake(robotsOk() + [SHOP.'/*' => Http::response('secret', 200)]);
    // robots.txt lookup sees a public address; the page lookup is rebound to loopback.
    $dns = (new FakeDnsResolver)->set('shop.acme-store.com', [PUBLIC_IP], ['127.0.0.1']);

    expect(fn () => safeClient($dns)->get(SHOP.'/products'))->toThrow(BlockedUrlException::class);
    Http::assertNotSent(fn (Request $r) => str_ends_with($r->url(), '/products'));
});

it('re-validates redirect targets', function (string $location, string $expected) {
    $this->dns->set('internal.acme-store.com', ['10.0.0.1']);
    Http::fake(robotsOk() + [SHOP.'/start' => Http::response('', 302, ['Location' => $location])]);

    expect(fn () => safeClient($this->dns)->get(SHOP.'/start'))
        ->toThrow(fn (BlockedUrlException $e) => expect($e->detail)->toBe($expected));
})->with([
    'to a private host' => ['http://internal.acme-store.com/admin', 'private_address'],
    'to cloud metadata' => ['http://169.254.169.254/latest/meta-data/', 'ip_literal'],
    'to localhost' => ['http://localhost:8000/', 'port'],
    'to a file URL' => ['file:///etc/passwd', 'scheme'],
    'to another port' => ['https://shop.acme-store.com:8443/', 'port'],
]);

it('follows safe redirects and reports the final URL', function () {
    $this->dns->set('www.acme-store.com', [PUBLIC_IP]);
    Http::fake(robotsOk() + [
        'https://www.acme-store.com/robots.txt' => Http::response('', 404),
        SHOP.'/old' => Http::response('', 301, ['Location' => 'https://www.acme-store.com/new']),
        'https://www.acme-store.com/new' => Http::response('hello', 200),
    ]);

    $response = safeClient($this->dns)->get(SHOP.'/old');

    expect($response->status)->toBe(200)
        ->and($response->url)->toBe('https://www.acme-store.com/new')
        ->and($response->redirects)->toBe(1);
});

it('stops after five redirects', function () {
    Http::fake(robotsOk() + [SHOP.'/*' => Http::response('', 302, ['Location' => '/loop'])]);

    expect(fn () => safeClient($this->dns)->get(SHOP.'/loop'))->toThrow(TooManyRedirectsException::class);
});

it('refuses unsafe URLs before any DNS lookup or request', function () {
    Http::fake();

    expect(fn () => safeClient($this->dns)->get('http://localhost/'))->toThrow(BlockedUrlException::class);
    expect($this->dns->lookups)->toBe([]);
    Http::assertNothingSent();
});

it('honours robots.txt disallow rules', function () {
    Http::fake(robotsOk("User-agent: *\nDisallow: /wp-json/\n") + [SHOP.'/*' => Http::response('x', 200)]);

    expect(fn () => safeClient($this->dns)->get(SHOP.'/wp-json/wc/store/v1/products'))->toThrow(RobotsDisallowedException::class);
    Http::assertNotSent(fn (Request $r) => str_contains($r->url(), '/wp-json/'));
});

it('treats a failing robots.txt as disallow and a missing one as allow', function () {
    Http::fake([SHOP.'/robots.txt' => Http::response('', 503)]);
    expect(fn () => safeClient($this->dns)->get(SHOP.'/shop'))->toThrow(RobotsDisallowedException::class);

    $this->dns->set('other-store.com', [PUBLIC_IP]);
    Http::fake([
        'https://other-store.com/robots.txt' => Http::response('', 404),
        'https://other-store.com/shop' => Http::response('ok', 200),
    ]);
    expect(safeClient($this->dns)->get('https://other-store.com/shop')->status)->toBe(200);
});

it('stops at access controls without retrying', function (int $status) {
    Http::fake(robotsOk() + [SHOP.'/private' => Http::response('no', $status)]);

    expect(fn () => safeClient($this->dns)->get(SHOP.'/private'))->toThrow(AccessDeniedException::class);
    expect(collect(Http::recorded())->filter(fn ($p) => str_ends_with($p[0]->url(), '/private')))->toHaveCount(1);
})->with([401, 403]);

it('detects bot challenge pages instead of solving them', function (array $response) {
    Http::fake(robotsOk() + [SHOP.'/shop' => Http::response(...$response)]);

    expect(fn () => safeClient($this->dns)->get(SHOP.'/shop'))->toThrow(ChallengeDetectedException::class);
})->with([
    'cloudflare interstitial' => [['<html><title>Just a moment...</title><script src="/cdn-cgi/challenge-platform/x"></script></html>', 403, ['Content-Type' => 'text/html']]],
    'cf-mitigated header' => [['', 403, ['cf-mitigated' => 'challenge']]],
    'challenge with 200' => [['<html><head><title>Just a moment...</title></head></html>', 200, ['Content-Type' => 'text/html; charset=UTF-8']]],
]);

it('does not mistake a normal page with a contact-form captcha for a challenge', function () {
    Http::fake(robotsOk() + [SHOP.'/contact' => Http::response('<html><title>Contact</title><div class="g-recaptcha"></div></html>', 200, ['Content-Type' => 'text/html'])]);

    expect(safeClient($this->dns)->get(SHOP.'/contact')->status)->toBe(200);
});

it('waits for Retry-After on 429 and then succeeds', function () {
    Http::fake(robotsOk() + [SHOP.'/p' => Http::sequence()
        ->push('slow down', 429, ['Retry-After' => '7'])
        ->push('ok', 200)]);

    $start = Carbon::now()->getTimestamp();
    $response = safeClient($this->dns)->get(SHOP.'/p');

    expect($response->status)->toBe(200)
        ->and(Carbon::now()->getTimestamp() - $start)->toBeGreaterThanOrEqual(7);
    Sleep::assertSlept(fn ($d) => (int) round($d->totalSeconds) === 7);
});

it('hands long Retry-After values back to the caller', function () {
    Http::fake(robotsOk() + [SHOP.'/p' => Http::response('', 429, ['Retry-After' => '3600'])]);

    expect(fn () => safeClient($this->dns)->get(SHOP.'/p'))
        ->toThrow(fn (RetryLaterException $e) => expect($e->retryAfterSeconds)->toBe(3600));
});

it('retries server errors with backoff', function () {
    Http::fake(robotsOk() + [SHOP.'/p' => Http::sequence()->push('', 500)->push('', 502)->push('ok', 200)]);

    expect(safeClient($this->dns)->get(SHOP.'/p')->body)->toBe('ok');
    expect(collect(Http::recorded())->filter(fn ($p) => str_ends_with($p[0]->url(), '/p')))->toHaveCount(3);
});

it('gives up after the maximum attempts', function () {
    Http::fake(robotsOk() + [SHOP.'/p' => Http::response('', 500)]);

    expect(fn () => safeClient($this->dns)->get(SHOP.'/p'))->toThrow(FetchFailedException::class);
    expect(collect(Http::recorded())->filter(fn ($p) => str_ends_with($p[0]->url(), '/p')))->toHaveCount(3);
});

it('aborts responses over the size cap', function () {
    Http::fake(robotsOk() + [SHOP.'/big' => Http::response(str_repeat('x', 2048), 200)]);

    expect(fn () => safeClient($this->dns, ['max_response_bytes' => 1024])->get(SHOP.'/big'))
        ->toThrow(ResponseTooLargeException::class);
});

it('returns ordinary error statuses for the caller to interpret', function () {
    Http::fake(robotsOk() + [SHOP.'/missing' => Http::response('nope', 404)]);

    expect(safeClient($this->dns)->get(SHOP.'/missing')->status)->toBe(404);
});

it('builds CURLOPT_RESOLVE pin entries for IPv4 and IPv6', function () {
    expect(SafeHttpClient::pinEntry('shop.acme-store.com', 443, '93.184.216.34'))->toBe('shop.acme-store.com:443:93.184.216.34')
        ->and(SafeHttpClient::pinEntry('shop.acme-store.com', 443, '2606:4700::1'))->toBe('shop.acme-store.com:443:[2606:4700::1]');
});
