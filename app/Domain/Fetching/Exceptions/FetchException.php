<?php

namespace App\Domain\Fetching\Exceptions;

use RuntimeException;

/**
 * Base class for every fetch failure. reason() is a stable machine-readable code that jobs
 * store and the UI turns into an explanation.
 */
abstract class FetchException extends RuntimeException
{
    abstract public function reason(): string;

    /** Whether trying again later could succeed. */
    public function isRetryable(): bool
    {
        return false;
    }
}
