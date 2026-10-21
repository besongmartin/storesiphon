<?php

namespace App\Domain\Extraction;

final class ExtractionSummary
{
    public int $products = 0;

    public int $variations = 0;

    /** @var array<string, true> */
    public array $methodsUsed = [];

    public ?string $lastMethod = null;

    /** @var list<string> */
    public array $warnings = [];

    public bool $partial = false;

    public bool $failed = false;

    public function finish(): self
    {
        return $this;
    }

    /** @return list<string> */
    public function methods(): array
    {
        return array_keys($this->methodsUsed);
    }

    /** succeeded | partial | failed (job state vocabulary from the architecture record) */
    public function status(): string
    {
        return $this->failed ? 'failed' : ($this->partial ? 'partial' : 'succeeded');
    }
}
