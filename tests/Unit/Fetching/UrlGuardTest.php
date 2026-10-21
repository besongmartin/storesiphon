<?php

use App\Domain\Fetching\Exceptions\BlockedUrlException;
use App\Domain\Fetching\UrlGuard;

it('rejects unsafe URLs', function (string $url, string $detail) {
    expect(fn () => (new UrlGuard)->check($url))
        ->toThrow(fn (BlockedUrlException $e) => expect($e->detail)->toBe($detail));
})->with([
    'file scheme' => ['file:///etc/passwd', 'scheme'],
    'ftp scheme' => ['ftp://shop.acme-store.com/', 'scheme'],
    'javascript' => ['javascript:alert(1)', 'invalid_url'],
    'gopher' => ['gopher://shop.acme-store.com/', 'scheme'],
    'credentials' => ['https://user:pass@shop.acme-store.com/', 'credentials'],
    'non-standard port' => ['https://shop.acme-store.com:8443/', 'port'],
    'redis port' => ['http://shop.acme-store.com:6379/', 'port'],
    'localhost' => ['http://localhost/', 'invalid_host'],
    'localhost subdomain' => ['http://api.localhost/', 'private_host'],
    'IPv4 literal' => ['http://127.0.0.1/', 'ip_literal'],
    'metadata literal' => ['http://169.254.169.254/latest/meta-data/', 'ip_literal'],
    'IPv6 literal' => ['http://[::1]/', 'ip_literal'],
    'decimal IP' => ['http://2130706433/', 'ip_literal'],
    'hex IP' => ['http://0x7f000001/', 'ip_literal'],
    'single label' => ['http://intranet/', 'invalid_host'],
    '.local' => ['http://printer.local/', 'private_host'],
    '.internal' => ['http://db.internal/', 'private_host'],
    'whitespace' => ['https://shop.acme-store.com/a b', 'invalid_url'],
    'backslash trick' => ['https://shop.acme-store.com\\@127.0.0.1/', 'invalid_url'],
    'empty' => ['', 'invalid_url'],
    'no host' => ['https:///path', 'invalid_url'],
]);

it('normalizes allowed URLs', function () {
    $safe = (new UrlGuard)->check('HTTPS://Shop.Acme-Store.com./products?page=2#reviews');

    expect($safe->url)->toBe('https://shop.acme-store.com/products?page=2')
        ->and($safe->host)->toBe('shop.acme-store.com')
        ->and($safe->port)->toBe(443)
        ->and($safe->pathAndQuery)->toBe('/products?page=2')
        ->and($safe->origin())->toBe('https://shop.acme-store.com');
});

it('converts internationalized domain names to ASCII', function () {
    expect((new UrlGuard)->check('https://bücher-laden.de/')->host)->toBe('xn--bcher-laden-thb.de');
});

it('resolves redirect locations against the current URL', function () {
    $safe = (new UrlGuard)->check('https://shop.acme-store.com/shop/page/2');

    expect($safe->resolve('/cart'))->toBe('https://shop.acme-store.com/cart')
        ->and($safe->resolve('3'))->toBe('https://shop.acme-store.com/shop/page/3')
        ->and($safe->resolve('//cdn.acme-store.com/x'))->toBe('https://cdn.acme-store.com/x')
        ->and($safe->resolve('http://other.com/'))->toBe('http://other.com/');
});
