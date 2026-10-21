<?php

namespace App\Domain\Fetching\Exceptions;

/**
 * The site asked us to slow down for longer than we wait inline (Retry-After too long).
 * The caller should re-queue the work after retryAfterSeconds.
 */
class RetryLaterException extends FetchException
{
    public function __construct(string $message, public readonly int $retryAfterSeconds)
    {
        parent::__construct($message);
    }

    public function reason(): string
    {
        return 'retry_later';
    }

    public function isRetryable(): bool
    {
        return true;
    }
}
