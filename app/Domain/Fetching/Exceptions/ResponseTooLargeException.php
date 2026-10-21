<?php

namespace App\Domain\Fetching\Exceptions;

/**
 * The response exceeded the configured size cap.
 */
class ResponseTooLargeException extends FetchException
{
    public function reason(): string
    {
        return 'response_too_large';
    }
}
