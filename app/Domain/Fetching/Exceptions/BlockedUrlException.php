<?php

namespace App\Domain\Fetching\Exceptions;

/**
 * URL or its resolved address is not allowed (SSRF guard).
 */
class BlockedUrlException extends FetchException
{
    public function __construct(string $message, public readonly string $detail = 'policy')
    {
        parent::__construct($message);
    }

    public function reason(): string
    {
        return 'blocked_url';
    }
}
