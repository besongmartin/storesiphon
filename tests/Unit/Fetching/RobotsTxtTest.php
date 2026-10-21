<?php

use App\Domain\Fetching\Robots\RobotsTxt;

it('uses the group for our agent over the wildcard group', function () {
    $robots = RobotsTxt::parse(<<<'TXT'
        User-agent: *
        Disallow: /

        User-agent: StoreSiphonBot
        Disallow: /checkout
        TXT, 'StoreSiphonBot');

    expect($robots->isAllowed('/product/hat'))->toBeTrue()
        ->and($robots->isAllowed('/checkout/step-1'))->toBeFalse();
});

it('falls back to the wildcard group', function () {
    $robots = RobotsTxt::parse("User-agent: Googlebot\nDisallow: /\n\nUser-agent: *\nDisallow: /cart\n", 'StoreSiphonBot');

    expect($robots->isAllowed('/shop'))->toBeTrue()
        ->and($robots->isAllowed('/cart'))->toBeFalse();
});

it('applies the longest match and lets Allow win ties', function () {
    $robots = RobotsTxt::parse(<<<'TXT'
        User-agent: *
        Disallow: /wp-admin/
        Allow: /wp-admin/admin-ajax.php
        Disallow: /wp-json/
        Allow: /wp-json/wc/store/
        Disallow: /page
        Allow: /page
        TXT, 'StoreSiphonBot');

    expect($robots->isAllowed('/wp-admin/options.php'))->toBeFalse()
        ->and($robots->isAllowed('/wp-admin/admin-ajax.php'))->toBeTrue()
        ->and($robots->isAllowed('/wp-json/wp/v2/users'))->toBeFalse()
        ->and($robots->isAllowed('/wp-json/wc/store/v1/products?per_page=100'))->toBeTrue()
        ->and($robots->isAllowed('/page'))->toBeTrue();
});

it('supports * and $ wildcards', function () {
    $robots = RobotsTxt::parse("User-agent: *\nDisallow: /*?add-to-cart=\nDisallow: /*.pdf$\n", 'StoreSiphonBot');

    expect($robots->isAllowed('/product/hat?add-to-cart=12'))->toBeFalse()
        ->and($robots->isAllowed('/product/hat'))->toBeTrue()
        ->and($robots->isAllowed('/files/catalog.pdf'))->toBeFalse()
        ->and($robots->isAllowed('/files/catalog.pdf?v=2'))->toBeTrue();
});

it('reads crawl-delay and sitemaps, ignoring comments and case', function () {
    $robots = RobotsTxt::parse(<<<'TXT'
        # shop robots
        USER-AGENT: *   # everyone
        crawl-delay: 10
        Disallow:
        Sitemap: https://shop.acme-store.com/wp-sitemap.xml
        sitemap: https://shop.acme-store.com/product-sitemap.xml
        TXT, 'StoreSiphonBot');

    expect($robots->crawlDelay)->toBe(10.0)
        ->and($robots->isAllowed('/anything'))->toBeTrue()
        ->and($robots->sitemaps)->toBe([
            'https://shop.acme-store.com/wp-sitemap.xml',
            'https://shop.acme-store.com/product-sitemap.xml',
        ]);
});

it('matches agent tokens case-insensitively and groups consecutive user-agent lines', function () {
    $robots = RobotsTxt::parse("User-agent: otherbot\nUser-agent: storesiphonbot\nDisallow: /private\n", 'StoreSiphonBot');

    expect($robots->isAllowed('/private/x'))->toBeFalse();
});

it('compares unreserved percent-escapes canonically', function () {
    $robots = RobotsTxt::parse("User-agent: *\nDisallow: /%7Eadmin\n", 'StoreSiphonBot');

    expect($robots->isAllowed('/~admin/x'))->toBeFalse();
});

it('has explicit allow-all and disallow-all states', function () {
    expect(RobotsTxt::allowAll()->isAllowed('/x'))->toBeTrue()
        ->and(RobotsTxt::disallowAll()->isAllowed('/x'))->toBeFalse()
        ->and(RobotsTxt::disallowAll()->isAllowed('/robots.txt'))->toBeFalse();
});
