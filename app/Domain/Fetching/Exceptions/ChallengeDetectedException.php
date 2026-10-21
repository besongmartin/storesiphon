<?php

namespace App\Domain\Fetching\Exceptions;

/**
 * The site served a bot challenge or CAPTCHA page; we stop instead of solving it.
 */
class ChallengeDetectedException extends FetchException
{
    public function reason(): string
    {
        return 'challenge_detected';
    }
}
