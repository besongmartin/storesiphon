<?php

namespace App\Domain\Export;

use App\Models\ExtractionJob;
use App\Models\Product;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use RuntimeException;

/**
 * Streaming file writers. Each returns the number of data rows written.
 */
final class Writers
{
    public static function for(string $format): string
    {
        return match ($format) {
            'csv' => 'csv',
            'xlsx' => 'xlsx',
            'json' => 'json',
            default => throw new RuntimeException("Unsupported export format {$format}"),
        };
    }

    /** CSV: UTF-8 with BOM (Excel opens accents correctly), CRLF line endings, formula-safe cells. */
    public static function csv(ExtractionJob $job, string $path, array $options = []): int
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException("Cannot write {$path}");
        }
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, array_values(ExportRows::COLUMNS), ',', '"', '', "\r\n");

        $count = 0;
        foreach ((new ExportRows($options))->rows($job) as $row) {
            fputcsv($handle, array_map(fn ($v) => ExportRows::safeCell($v) ?? '', array_values($row)), ',', '"', '', "\r\n");
            $count++;
        }
        fclose($handle);

        return $count;
    }

    /** XLSX via OpenSpout (streaming): numbers as numeric cells, text formula-safe, bold header. */
    public static function xlsx(ExtractionJob $job, string $path, array $options = []): int
    {
        $writer = new XlsxWriter;
        $writer->openToFile($path);
        $writer->getCurrentSheet()->setName('Products');
        $writer->addRow(Row::fromValues(array_values(ExportRows::COLUMNS), (new Style)->setFontBold()));

        $count = 0;
        foreach ((new ExportRows($options))->rows($job) as $row) {
            $cells = [];
            foreach ($row as $key => $value) {
                if ($value !== null && in_array($key, ExportRows::NUMERIC, true) && is_numeric($value)) {
                    $cells[] = Cell::fromValue(str_contains($value, '.') ? (float) $value : (int) $value);
                } else {
                    $cells[] = Cell::fromValue(ExportRows::safeCell($value) ?? '');
                }
            }
            $writer->addRow(new Row($cells));
            $count++;
        }
        $writer->close();

        return $count;
    }

    /** JSON: nested products with their variations, streamed product by product. */
    public static function json(ExtractionJob $job, string $path, array $options = []): int
    {
        $handle = fopen($path, 'wb');
        if ($handle === false) {
            throw new RuntimeException("Cannot write {$path}");
        }
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION;
        $meta = [
            'store' => $job->preflight['origin'] ?? $job->input_url,
            'extracted_at' => $job->finished_at?->toIso8601String(),
            'status' => $job->status,
            'methods' => $job->methods_used ? explode(',', $job->methods_used) : [],
        ];
        fwrite($handle, '{"meta":'.json_encode($meta, $flags).',"products":[');

        $rows = new ExportRows($options);
        $count = 0;
        $query = Product::query()->where('extraction_job_id', $job->id)->whereNull('parent_id')->with('variations');
        foreach ($query->lazyById(200) as $product) {
            $item = self::jsonProduct($product, $rows);
            $item['variations'] = $product->variations->map(fn (Product $v) => self::jsonProduct($v, $rows, $product))->all();
            fwrite($handle, ($count > 0 ? ',' : '')."\n".json_encode($item, $flags));
            $count++;
        }
        fwrite($handle, "\n]}\n");
        fclose($handle);

        return $count;
    }

    /** @return array<string, mixed> */
    private static function jsonProduct(Product $p, ExportRows $rows, ?Product $parent = null): array
    {
        $flat = $rows->row($p, $parent);
        $num = fn (?string $v) => $v === null ? null : $v;

        return array_filter([
            'id' => $flat['id'],
            'type' => $flat['type'],
            'sku' => $flat['sku'],
            'name' => $flat['name'],
            'variation_attributes' => $p->variation_attributes ?: null,
            'regular_price' => $num($flat['regular_price']),
            'sale_price' => $num($flat['sale_price']),
            'price' => $num($flat['price']),
            'price_min' => $num($flat['price_min']),
            'price_max' => $num($flat['price_max']),
            'currency' => $flat['currency'],
            'stock_status' => $flat['stock_status'],
            'stock_quantity' => $p->stock_quantity,
            'categories' => $p->categories ?: null,
            'tags' => $p->tags ?: null,
            'brands' => $p->brands ?: null,
            'attributes' => $p->attributes ?: null,
            'images' => $p->images ?: null,
            'short_description' => $flat['short_description'],
            'description' => $flat['description'],
            'url' => $flat['url'],
            'rating' => $p->rating_average,
            'review_count' => $p->review_count,
        ], fn ($v) => $v !== null);
    }
}
