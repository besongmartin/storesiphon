<?php

namespace Tests\Support;

use App\Domain\Fetching\Dns\DnsResolver;
use Closure;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * A simulated WooCommerce store served through Http::fake, built from tests/Fixtures/woo.
 *
 * Default behaviour: robots.txt allows everything, Store API works (2 pages), sitemaps and product
 * pages exist. Tests override individual routes with on() to simulate restrictions and failures.
 */
final class FakeWooStore
{
    public const ORIGIN = 'https://shop.acme-store.com';

    /** @var array<string, Closure(Request): (PromiseInterface|null)> */
    private array $overrides = [];

    /** @var list<string> */
    public array $requested = [];

    /** Modern stores answer ?type=variation&parent=… in bulk; set false to simulate a store that ignores the filter. */
    public bool $bulkVariations = true;

    public function __construct(private string $robots = "User-agent: *\nAllow: /\nSitemap: https://shop.acme-store.com/wp-sitemap.xml\n") {}

    public function withoutBulkVariations(): self
    {
        $this->bulkVariations = false;

        return $this;
    }

    public static function fixture(string $name): string
    {
        return file_get_contents(base_path('tests/Fixtures/woo/'.$name));
    }

    /** Override a route. $match is a path prefix ("/wp-json/") or a full path+query ("/wp-json/wc/store/v1/products?page=2"). */
    public function on(string $match, Closure|PromiseInterface $response): self
    {
        $this->overrides[$match] = $response instanceof Closure ? $response : fn () => $response;

        return $this;
    }

    public function robots(string $content): self
    {
        $this->robots = $content;

        return $this;
    }

    /** Install the fake HTTP layer and a DNS resolver that maps the store to a public IP. */
    public function install(): self
    {
        $dns = (new FakeDnsResolver)->set('shop.acme-store.com', ['93.184.216.34']);
        app()->instance(DnsResolver::class, $dns);

        Http::fake(fn (Request $request) => $this->respond($request));

        return $this;
    }

    public function wasRequested(string $needle): bool
    {
        foreach ($this->requested as $url) {
            if (str_contains($url, $needle)) {
                return true;
            }
        }

        return false;
    }

    private function respond(Request $request): PromiseInterface
    {
        $url = $request->url();
        $this->requested[] = $url;
        $parts = parse_url($url);
        $path = $parts['path'] ?? '/';
        $query = [];
        parse_str($parts['query'] ?? '', $query);
        $pathAndQuery = $path.(isset($parts['query']) ? '?'.$parts['query'] : '');

        foreach ($this->overrides as $match => $handler) {
            if ($pathAndQuery === $match || str_starts_with($pathAndQuery, $match)) {
                if (($response = $handler($request)) !== null) {
                    return $response;
                }
            }
        }

        // ?rest_route= form maps onto the same API
        if ($path === '/' && isset($query['rest_route'])) {
            $path = '/wp-json'.$query['rest_route'];
        }

        return match (true) {
            $path === '/robots.txt' => Http::response($this->robots, 200, ['Content-Type' => 'text/plain']),
            $path === '/' => Http::response(self::fixture('home.html'), 200, ['Content-Type' => 'text/html; charset=UTF-8']),
            $path === '/wp-json/wc/store/v1/products' && ($query['type'] ?? null) === 'variation' && $this->bulkVariations => $this->variationsOf(array_map('intval', explode(',', (string) ($query['parent'] ?? '')))),
            $path === '/wp-json/wc/store/v1/products' => $this->productsPage((int) ($query['page'] ?? 1), (int) ($query['per_page'] ?? 100)),
            (bool) preg_match('#^/wp-json/wc/store/v1/products/(\d+)$#', $path, $m) => $this->json('store-api-variation-'.$m[1].'.json'),
            $path === '/wp-sitemap.xml' => Http::response(self::fixture('wp-sitemap.xml'), 200, ['Content-Type' => 'application/xml']),
            $path === '/wp-sitemap-posts-product-1.xml' => Http::response(self::fixture('wp-sitemap-posts-product-1.xml'), 200, ['Content-Type' => 'application/xml']),
            (bool) preg_match('#^/product/([a-z-]+)/$#', $path, $m) => $this->page('product-'.$m[1].'.html'),
            default => Http::response('<html><body>Not found</body></html>', 404, ['Content-Type' => 'text/html']),
        };
    }

    private function productsPage(int $page, int $perPage): PromiseInterface
    {
        if ($perPage === 1) {
            $first = array_slice(json_decode(self::fixture('store-api-products-p1.json'), true), 0, 1);

            return Http::response($first, 200, ['X-WP-Total' => '4', 'X-WP-TotalPages' => '4']);
        }
        $file = "store-api-products-p{$page}.json";
        if (! file_exists(base_path('tests/Fixtures/woo/'.$file))) {
            return Http::response(['code' => 'rest_post_invalid_page_number'], 400);
        }

        return Http::response(json_decode(self::fixture($file), true), 200, ['X-WP-Total' => '4', 'X-WP-TotalPages' => '2']);
    }

    /** @param list<int> $parents */
    private function variationsOf(array $parents): PromiseInterface
    {
        $rows = [];
        foreach (glob(base_path('tests/Fixtures/woo/store-api-variation-*.json')) as $file) {
            $row = json_decode(file_get_contents($file), true);
            if (in_array((int) $row['parent'], $parents, true)) {
                $rows[] = $row;
            }
        }

        return Http::response($rows, 200, ['X-WP-Total' => (string) count($rows), 'X-WP-TotalPages' => '1']);
    }

    private function json(string $file): PromiseInterface
    {
        return file_exists(base_path('tests/Fixtures/woo/'.$file))
            ? Http::response(json_decode(self::fixture($file), true), 200)
            : Http::response(['code' => 'woocommerce_rest_product_invalid_id'], 404);
    }

    private function page(string $file): PromiseInterface
    {
        return file_exists(base_path('tests/Fixtures/woo/'.$file))
            ? Http::response(self::fixture($file), 200, ['Content-Type' => 'text/html; charset=UTF-8'])
            : Http::response('<html>Not found</html>', 404, ['Content-Type' => 'text/html']);
    }
}
