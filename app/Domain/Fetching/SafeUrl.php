<?php

namespace App\Domain\Fetching;

/**
 * A URL that passed UrlGuard. Immutable; resolve relative redirects with resolve().
 */
final class SafeUrl
{
    public function __construct(
        public readonly string $url,
        public readonly string $scheme,
        public readonly string $host,
        public readonly int $port,
        public readonly string $pathAndQuery,
    ) {}

    public function origin(): string
    {
        $default = ($this->scheme === 'https' && $this->port === 443) || ($this->scheme === 'http' && $this->port === 80);

        return $this->scheme.'://'.$this->host.($default ? '' : ':'.$this->port);
    }

    /** Resolve a (possibly relative) Location header against this URL. Result still needs UrlGuard. */
    public function resolve(string $location): string
    {
        $location = trim($location);
        if (preg_match('#^[a-z][a-z0-9+.-]*:#i', $location)) {
            return $location;
        }
        if (str_starts_with($location, '//')) {
            return $this->scheme.':'.$location;
        }
        if (str_starts_with($location, '/')) {
            return $this->origin().$location;
        }
        $path = parse_url($this->url, PHP_URL_PATH) ?: '/';
        $dir = substr($path, 0, strrpos($path, '/') + 1);

        return $this->origin().$dir.$location;
    }

    public function __toString(): string
    {
        return $this->url;
    }
}
