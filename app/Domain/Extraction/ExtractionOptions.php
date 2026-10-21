<?php

namespace App\Domain\Extraction;

final class ExtractionOptions
{
    /**
     * @param  list<int>  $categoryIds  Store API category IDs to limit to (empty = all; from preflight)
     */
    public function __construct(
        public readonly int $maxProducts = 25,
        public readonly bool $includeVariations = true,
        public readonly array $categoryIds = [],
        public readonly int $perPage = 100,
    ) {}
}
