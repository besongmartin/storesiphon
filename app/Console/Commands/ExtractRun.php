<?php

namespace App\Console\Commands;

use App\Domain\Extraction\Data\ProductData;
use App\Domain\Extraction\ExtractionOptions;
use App\Domain\Extraction\Extractor;
use App\Domain\Extraction\Preflight\Preflight;
use App\Domain\Extraction\Preflight\PreflightResult;
use App\Domain\Fetching\Exceptions\FetchException;
use Illuminate\Console\Command;

/**
 * Diagnostic: preflight a store and extract a few products without saving anything.
 * Use only on stores you are allowed to scrape; all fetcher politeness rules apply.
 */
class ExtractRun extends Command
{
    protected $signature = 'extract:run
        {store : Store URL or domain}
        {--max=5 : Maximum products}
        {--no-variations : Skip variation requests}
        {--method= : Force one method (store_api, rest_route, sitemap) for testing}
        {--json= : Write the extracted products to this JSON file}';

    protected $description = 'Preflight a WooCommerce store and extract a few products (nothing is saved)';

    public function handle(Preflight $preflight, Extractor $extractor): int
    {
        $result = $preflight->run((string) $this->argument('store'));

        $this->info('Preflight: '.$result->status);
        $this->table(['Field', 'Value'], [
            ['Origin', $result->origin],
            ['Store', $result->storeName ?? '—'],
            ['WooCommerce signals', implode(', ', $result->signals) ?: '—'],
            ['Methods', implode(' → ', $result->methods) ?: '—'],
            ['Estimated products', $result->estimatedProducts ?? '—'],
            ['Currency', $result->currency ?? '—'],
            ['Crawl-delay', $result->crawlDelay ?? '—'],
            ['Reason', $result->reasonCode ? $result->reasonCode.': '.$result->message : '—'],
        ]);
        foreach ($result->notes as $note) {
            $this->line('  · '.$note);
        }

        if ($forced = $this->option('method')) {
            $result = new PreflightResult(...array_merge($result->toArray(), ['methods' => [$forced]]));
        }
        if (! $result->canExtract()) {
            return self::FAILURE;
        }

        $all = [];
        try {
            $summary = $extractor->run(
                $result,
                new ExtractionOptions(maxProducts: (int) $this->option('max'), includeVariations: ! $this->option('no-variations')),
                function (array $batch) use (&$all) {
                    foreach ($batch as $product) {
                        $all[] = $product;
                    }
                    $this->line('  received '.count($batch).' product(s)');
                },
            );
        } catch (FetchException $e) {
            $this->error("Stopped: {$e->reason()} — {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info("Extraction: {$summary->status()} · {$summary->products} products · {$summary->variations} variations · via ".implode(', ', $summary->methods()));
        foreach ($summary->warnings as $w) {
            $this->warn('  '.$w);
        }

        $rows = [];
        foreach ($all as $p) {
            $rows[] = $this->row($p);
            foreach ($p->variations as $v) {
                $rows[] = $this->row($v, '  ↳ ');
            }
        }
        $this->table(['ID', 'Type', 'Name', 'SKU', 'Regular', 'Sale', 'Cur', 'Stock'], $rows);

        if ($file = $this->option('json')) {
            file_put_contents($file, json_encode(array_map(fn (ProductData $p) => $p->toArray(), $all), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
            $this->line("Saved to {$file}");
        }

        return self::SUCCESS;
    }

    private function row(ProductData $p, string $prefix = ''): array
    {
        return [
            $p->externalId,
            $p->type,
            $prefix.mb_strimwidth($p->name, 0, 60, '…'),
            $p->sku ?? '—',
            $p->regularPrice ?? ($p->priceMin ? $p->priceMin.'–'.$p->priceMax : '—'),
            $p->salePrice ?? '—',
            $p->currency ?? '—',
            $p->stockStatus.($p->stockQuantity !== null ? " ({$p->stockQuantity})" : ''),
        ];
    }
}
