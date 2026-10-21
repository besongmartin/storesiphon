<?php

use App\Domain\Extraction\Data\ProductData;
use App\Domain\Extraction\ExtractionOptions;
use App\Domain\Extraction\ExtractionSummary;
use App\Domain\Extraction\Extractor;
use App\Domain\Extraction\Preflight\Preflight;
use App\Domain\Extraction\Preflight\PreflightResult;
use App\Domain\Fetching\Exceptions\ChallengeDetectedException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Support\FakeWooStore;

beforeEach(function () {
    Carbon::setTestNow('2026-10-21 12:00:00');
    Sleep::fake(syncWithCarbon: true);
    config(['cache.default' => 'array']);
});

/** @return array{0: PreflightResult, 1: list<ProductData>, 2: ExtractionSummary} */
function runStore(FakeWooStore $store, int $max = 25, bool $variations = true, ?string $input = 'shop.acme-store.com'): array
{
    $store->install();
    $preflight = app(Preflight::class)->run($input);
    if (! $preflight->canExtract()) {
        return [$preflight, [], null];
    }
    $products = [];
    $summary = app(Extractor::class)->run($preflight, new ExtractionOptions(maxProducts: $max, includeVariations: $variations), function (array $batch) use (&$products) {
        array_push($products, ...$batch);
    });

    return [$preflight, $products, $summary];
}

function bySku(array $products, string $sku): ProductData
{
    foreach ($products as $p) {
        if ($p->sku === $sku) {
            return $p;
        }
        foreach ($p->variations as $v) {
            if ($v->sku === $sku) {
                return $v;
            }
        }
    }
    throw new RuntimeException("No product with SKU {$sku}");
}

it('preflights a WooCommerce store with the Store API and sitemaps', function () {
    [$preflight] = runStore(new FakeWooStore, max: 0);

    expect($preflight->status)->toBe(PreflightResult::OK)
        ->and($preflight->origin)->toBe(FakeWooStore::ORIGIN)
        ->and($preflight->methods)->toBe(['store_api', 'sitemap'])
        ->and($preflight->estimatedProducts)->toBe(4)
        ->and($preflight->currency)->toBe('EUR')
        ->and($preflight->storeName)->toBe('Acme Hats')
        ->and($preflight->signals)->toContain('store_api', 'woocommerce_generator', 'product_sitemap');
});

it('extracts every product through the Store API, across pages', function () {
    [, $products, $summary] = runStore(new FakeWooStore);

    expect($summary->status())->toBe('succeeded')
        ->and($summary->methods())->toBe(['store_api'])
        ->and(array_map(fn ($p) => $p->sku, $products))->toBe(['HAT-WOOL', 'HAT-STRAW', 'CAP-VAR', 'HOOD-1'])
        ->and($summary->variations)->toBe(2);
});

it('normalizes prices, sales, currency, stock and text', function () {
    [, $products] = runStore(new FakeWooStore);

    $beanie = bySku($products, 'HAT-WOOL');
    expect($beanie->name)->toBe('Wool Beanie – Winter')
        ->and($beanie->regularPrice)->toBe('25.00')
        ->and($beanie->salePrice)->toBe('19.99')
        ->and($beanie->price)->toBe('19.99')
        ->and($beanie->currency)->toBe('EUR')
        ->and($beanie->stockStatus)->toBe(ProductData::STOCK_IN)
        ->and($beanie->stockQuantity)->toBe(3)
        ->and($beanie->categories)->toBe([['name' => 'Hats', 'slug' => 'hats']])
        ->and($beanie->tags[0]['name'])->toBe('Winter')
        ->and($beanie->brands)->toBe(['Acme'])
        ->and($beanie->images[0]['src'])->toBe(FakeWooStore::ORIGIN.'/wp-content/uploads/wool-beanie.jpg')
        ->and($beanie->ratingAverage)->toBe(4.5)
        ->and($beanie->descriptionText)->toBe("About\nWarm & soft.\nx care");

    // sanitized HTML: no script, no javascript: links, no event handlers
    expect($beanie->descriptionHtml)->not->toContain('<script')->not->toContain('javascript:')->not->toContain('onclick')
        ->toContain('<a href="https://acme-store.com/care" rel="nofollow noopener">care</a>');
});

it('does not report a sale when the Store API repeats the regular price', function () {
    [, $products] = runStore(new FakeWooStore);

    $straw = bySku($products, 'HAT-STRAW');
    expect($straw->regularPrice)->toBe('15.00')
        ->and($straw->salePrice)->toBeNull()
        ->and($straw->isOnSale())->toBeFalse()
        ->and($straw->stockStatus)->toBe(ProductData::STOCK_OUT);

    expect(bySku($products, 'HOOD-1')->stockStatus)->toBe(ProductData::STOCK_BACKORDER);
});

it('resolves variations with their own SKU, prices, sale and attributes', function () {
    [, $products] = runStore(new FakeWooStore);

    $cap = bySku($products, 'CAP-VAR');
    expect($cap->type)->toBe(ProductData::TYPE_VARIABLE)
        ->and($cap->regularPrice)->toBeNull()
        ->and($cap->priceMin)->toBe('12.00')
        ->and($cap->priceMax)->toBe('14.00')
        ->and($cap->attributes)->toBe([['name' => 'Colour', 'options' => ['Red', 'Blue'], 'variation' => true]])
        ->and($cap->variations)->toHaveCount(2);

    $red = bySku($products, 'CAP-RED');
    $blue = bySku($products, 'CAP-BLUE');
    expect($red->parentExternalId)->toBe('103')
        ->and($red->variationAttributes)->toBe(['Colour' => 'Red'])
        ->and($red->name)->toBe('Baseball Cap – Red')
        ->and($red->salePrice)->toBeNull()
        ->and($red->stockQuantity)->toBe(12)
        ->and($blue->regularPrice)->toBe('14.00')
        ->and($blue->salePrice)->toBe('11.00');
});

it('respects the product cap and can skip variations', function () {
    [, $products, $summary] = runStore(new FakeWooStore, max: 2, variations: false);

    expect($products)->toHaveCount(2)->and($summary->variations)->toBe(0);
});

it('uses public product pages when the store restricts its API (403), without trying rest_route', function () {
    $store = (new FakeWooStore)->on('/wp-json/', Http::response(['code' => 'rest_forbidden'], 403));

    [$preflight, $products, $summary] = runStore($store);

    expect($preflight->methods)->toBe(['sitemap'])
        ->and($preflight->notes[0])->toContain('restricts its API')
        ->and($summary->methods())->toBe(['sitemap'])
        ->and($store->wasRequested('rest_route'))->toBeFalse()
        ->and($store->wasRequested('elsewhere.example-cdn.com'))->toBeFalse();

    $beanie = bySku($products, 'HAT-WOOL');
    expect($beanie->name)->toBe('Wool Beanie – Winter')
        ->and($beanie->regularPrice)->toBe('25.00')
        ->and($beanie->salePrice)->toBe('19.99')
        ->and($beanie->currency)->toBe('EUR')
        ->and($beanie->stockQuantity)->toBe(3)
        ->and($beanie->externalId)->toBe('101')
        ->and($beanie->categories)->toBe([['name' => 'Hats', 'slug' => 'hats']])
        ->and($beanie->descriptionHtml)->not->toContain('<script');

    $blue = bySku($products, 'CAP-BLUE');
    expect($blue->regularPrice)->toBe('14.00')->and($blue->salePrice)->toBe('11.00')
        ->and($blue->stockStatus)->toBe(ProductData::STOCK_OUT)
        ->and($blue->variationAttributes)->toBe(['Colour' => 'Blue']);
    expect(bySku($products, 'CAP-VAR')->attributes)->toContain(['name' => 'Material', 'options' => ['Cotton'], 'variation' => false]);
    expect(bySku($products, 'HAT-STRAW')->stockStatus)->toBe(ProductData::STOCK_OUT);
});

it('honours robots.txt that disallows the API, falling back to pages', function () {
    $store = (new FakeWooStore)->robots("User-agent: *\nDisallow: /wp-json/\nSitemap: https://shop.acme-store.com/wp-sitemap.xml\n");

    [$preflight, , $summary] = runStore($store);

    expect($preflight->methods)->toBe(['sitemap'])
        ->and($summary->methods())->toBe(['sitemap'])
        ->and($store->wasRequested('/wp-json/'))->toBeFalse()
        ->and($store->wasRequested('rest_route'))->toBeFalse();
});

it('uses ?rest_route= only when pretty API URLs are not routed', function () {
    $store = (new FakeWooStore)->on('/wp-json/', Http::response('<html>Page not found</html>', 404, ['Content-Type' => 'text/html']));

    [$preflight, $products, $summary] = runStore($store);

    expect($preflight->methods)->toBe(['rest_route', 'sitemap'])
        ->and($summary->methods())->toBe(['rest_route'])
        ->and($products)->toHaveCount(4);
});

it('falls back to the sitemap when the API fails mid-run, without duplicates', function () {
    $store = (new FakeWooStore)->on('/wp-json/wc/store/v1/products?per_page=100&orderby=id&order=asc&page=2', Http::response('', 500));

    [, $products, $summary] = runStore($store);

    $skus = array_map(fn ($p) => $p->sku, $products);
    expect($summary->methods())->toBe(['store_api'])
        ->and($summary->warnings[0])->toContain('store_api stopped')
        ->and($summary->status())->toBe('partial')
        ->and($skus)->toBe(array_values(array_unique($skus)))
        ->and($skus)->toBe(['HAT-WOOL', 'HAT-STRAW', 'CAP-VAR']);

    // The sitemap fallback ran but skipped pages for products the API had already delivered.
    expect($store->wasRequested('/wp-sitemap-posts-product-1.xml'))->toBeTrue()
        ->and($store->wasRequested('/product/wool-beanie/'))->toBeFalse()
        ->and($store->wasRequested('/product/baseball-cap/'))->toBeFalse();
});

it('reports a site that is not WooCommerce', function () {
    $store = (new FakeWooStore)
        ->on('/', fn ($r) => parse_url($r->url(), PHP_URL_PATH) === '/' && ! str_contains($r->url(), 'rest_route') ? Http::response(FakeWooStore::fixture('home-blog.html'), 200, ['Content-Type' => 'text/html']) : null)
        ->on('/wp-json/', Http::response(['code' => 'rest_no_route'], 404))
        ->on('/?rest_route=', Http::response(['code' => 'rest_no_route'], 404))
        ->on('/wp-sitemap', Http::response('', 404));

    [$preflight] = runStore($store);

    expect($preflight->status)->toBe(PreflightResult::NOT_WOOCOMMERCE)
        ->and($preflight->canExtract())->toBeFalse()
        ->and($preflight->message)->toContain('does not appear to run WooCommerce');
});

it('stops when the store blocks us', function (int $status, string $reason, array $headers) {
    $store = (new FakeWooStore)->on('/', fn ($r) => parse_url($r->url(), PHP_URL_PATH) === '/' ? Http::response('<html><title>Just a moment...</title></html>', $status, $headers) : null);

    [$preflight] = runStore($store);

    expect($preflight->status)->toBe(PreflightResult::BLOCKED)->and($preflight->reasonCode)->toBe($reason);
})->with([
    'access denied' => [403, 'access_denied', ['Content-Type' => 'text/plain']],
    'bot challenge' => [503, 'challenge_detected', ['Content-Type' => 'text/html']],
]);

it('stops the run when product pages turn into a bot challenge', function () {
    $store = (new FakeWooStore)
        ->on('/wp-json/', Http::response(['code' => 'rest_forbidden'], 403))
        ->on('/product/baseball-cap/', Http::response('', 403, ['cf-mitigated' => 'challenge']));

    expect(fn () => runStore($store))->toThrow(ChallengeDetectedException::class);
});

it('rejects unsafe store addresses during preflight', function () {
    [$preflight] = runStore(new FakeWooStore, input: 'http://localhost:8080/');

    expect($preflight->status)->toBe(PreflightResult::BLOCKED)->and($preflight->reasonCode)->toBe('invalid_url');
});
