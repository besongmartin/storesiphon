<?php

namespace App\Domain\Extraction;

use Closure;

final class ExtractionOptions
{
    /**
     * @param  list<int>  $categoryIds  Store API category IDs to limit to (empty = all; from preflight)
     * @param  (Closure(): bool)|null  $shouldStop  polled before every network request; true cancels the run
     */
    public function __construct(
        public readonly int $maxProducts = 25,
        public readonly bool $includeVariations = true,
        public readonly array $categoryIds = [],
        public readonly int $perPage = 100,
        public readonly ?Closure $shouldStop = null,
    ) {}

    /** Called by sources before each request. */
    public function checkpoint(): void
    {
        if ($this->shouldStop !== null && ($this->shouldStop)()) {
            throw new ExtractionCancelled('Extraction cancelled');
        }
    }

    public function withMaxProducts(int $max): self
    {
        return new self($max, $this->includeVariations, $this->categoryIds, $this->perPage, $this->shouldStop);
    }
}
