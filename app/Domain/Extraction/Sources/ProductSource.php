<?php

namespace App\Domain\Extraction\Sources;

use App\Domain\Extraction\Data\ProductData;
use App\Domain\Extraction\ExtractionOptions;
use Generator;

/**
 * One rung of the extraction ladder (Store API, rest_route, sitemap + page parsing …).
 */
interface ProductSource
{
    /** Method name recorded on every product (store_api, rest_route, sitemap). */
    public function method(): string;

    /**
     * Yield batches of normalized products. Implementations stop by themselves when the catalogue
     * is exhausted; the caller enforces the overall product cap.
     *
     * @return Generator<int, list<ProductData>>
     */
    public function pages(ExtractionOptions $options): Generator;
}
