<?php

use App\Domain\Export\Writers;
use App\Domain\Extraction\JobRunner;
use App\Models\ExtractionJob;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\Support\FakeWooStore;

/**
 * Acceptance criterion: a 5,000-product catalogue extracts and exports within a bounded memory budget.
 */
it('extracts and exports 5,000 products within the memory budget', function () {
    Carbon::setTestNow('2026-10-21 12:00:00');
    Sleep::fake(syncWithCarbon: true);

    $total = 5000;
    $perPage = 100;
    $store = (new FakeWooStore)->on('/wp-json/wc/store/v1/products', function (Request $r) use ($total, $perPage) {
        parse_str((string) parse_url($r->url(), PHP_URL_QUERY), $q);
        $page = (int) ($q['page'] ?? 1);
        $size = (int) ($q['per_page'] ?? $perPage);
        $items = [];
        for ($i = ($page - 1) * $size + 1; $i <= min($total, $page * $size); $i++) {
            $items[] = [
                'id' => $i, 'name' => "Product {$i}", 'slug' => "product-{$i}", 'type' => 'simple',
                'permalink' => FakeWooStore::ORIGIN."/product/product-{$i}/", 'sku' => sprintf('SKU-%05d', $i),
                'description' => '<p>'.str_repeat('Lorem ipsum dolor sit amet. ', 10).'</p>',
                'on_sale' => false, 'is_in_stock' => true,
                'prices' => ['price' => '1999', 'regular_price' => '1999', 'sale_price' => '1999', 'currency_code' => 'USD', 'currency_minor_unit' => 2],
                'categories' => [['id' => 1, 'name' => 'Things', 'slug' => 'things']],
                'images' => [['src' => FakeWooStore::ORIGIN."/img/{$i}.jpg", 'alt' => '']],
            ];
        }

        return Http::response($items, 200, ['X-WP-Total' => (string) $total, 'X-WP-TotalPages' => (string) ceil($total / $size)]);
    })->install();

    // The HTTP fake keeps every response for assertions; turn that off so it does not distort the measurement.
    (fn () => $this->recording = false)->call(app(Factory::class));

    $job = ExtractionJob::create([
        'user_id' => User::factory()->create()->id, 'input_url' => FakeWooStore::ORIGIN.'/', 'status' => ExtractionJob::QUEUED,
        'options' => ['max_products' => $total, 'include_variations' => true],
    ]);

    gc_collect_cycles();
    memory_reset_peak_usage();
    $before = memory_get_usage();

    app(JobRunner::class)->run($job);
    $extractPeak = memory_get_peak_usage() - $before;

    $path = tempnam(sys_get_temp_dir(), 'ss-large-');
    memory_reset_peak_usage();
    $before = memory_get_usage();
    $rows = Writers::csv($job->refresh(), $path);
    $exportPeak = memory_get_peak_usage() - $before;
    @unlink($path);

    expect($job->status)->toBe(ExtractionJob::SUCCEEDED)
        ->and($job->products_found)->toBe($total)
        ->and(Product::where('extraction_job_id', $job->id)->count())->toBe($total)
        ->and($rows)->toBe($total)
        ->and($store->wasRequested('page=50'))->toBeTrue()
        ->and($extractPeak)->toBeLessThan(64 * 1024 * 1024)
        ->and($exportPeak)->toBeLessThan(32 * 1024 * 1024);
})->group('slow');
