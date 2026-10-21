<?php

namespace App\Domain\Fetching\Exceptions;

/**
 * Network failure or server error that persisted after retries.
 */
class FetchFailedException extends FetchException
{
    public function reason(): string
    {
        return 'fetch_failed';
    }

    public function isRetryable(): bool
    {
        return true;
    }
}
