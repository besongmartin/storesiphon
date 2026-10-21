<?php

namespace App\Domain\Fetching\Exceptions;

/**
 * More redirects than allowed.
 */
class TooManyRedirectsException extends FetchException
{
    public function reason(): string
    {
        return 'too_many_redirects';
    }
}
