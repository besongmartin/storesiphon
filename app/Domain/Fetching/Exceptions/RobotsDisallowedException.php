<?php

namespace App\Domain\Fetching\Exceptions;

/**
 * robots.txt disallows this URL for our user agent.
 */
class RobotsDisallowedException extends FetchException
{
    public function reason(): string
    {
        return 'robots_disallowed';
    }
}
