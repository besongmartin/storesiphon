<?php

namespace App\Domain\Fetching;

/**
 * Result of a SafeHttpClient request (after redirects).
 */
final class FetchResponse
{
    /**
     * @param  array<string, list<string>>  $headers  lower-cased header names
     */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly string $body,
        public readonly string $url,
        public readonly ?string $ip = null,
        public readonly int $redirects = 0,
    ) {}

    public function successful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }

    public function json(): mixed
    {
        return json_decode($this->body, true, 512, JSON_BIGINT_AS_STRING);
    }

    public function isJson(): bool
    {
        return str_contains((string) $this->header('content-type'), 'json');
    }
}
