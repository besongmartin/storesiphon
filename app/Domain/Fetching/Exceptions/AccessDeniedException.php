<?php

namespace App\Domain\Fetching\Exceptions;

/**
 * The site answered 401/403: an access control we must not bypass.
 */
class AccessDeniedException extends FetchException
{
    public function reason(): string
    {
        return 'access_denied';
    }
}
