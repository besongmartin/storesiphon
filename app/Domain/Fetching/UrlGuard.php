<?php

namespace App\Domain\Fetching;

use App\Domain\Fetching\Exceptions\BlockedUrlException;

/**
 * Normalizes and validates a URL before any network activity.
 *
 * Allowed: http/https, a registrable public hostname, standard ports only, no credentials.
 * IP-literal hosts are refused outright (stores are addressed by name); resolved addresses are
 * checked separately by SafeHttpClient so DNS cannot be used to sneak around this guard.
 */
final class UrlGuard
{
    /** Host suffixes that never point at the public internet. */
    private const BLOCKED_SUFFIXES = [
        'localhost', 'local', 'localdomain', 'internal', 'intranet', 'lan', 'home', 'corp',
        'home.arpa', 'arpa', 'test', 'invalid', 'example', 'onion',
    ];

    /** @param list<int> $allowedPorts */
    public function __construct(private readonly array $allowedPorts = [80, 443]) {}

    public function check(string $url): SafeUrl
    {
        $url = trim($url);

        if ($url === '' || strlen($url) > 2048) {
            throw new BlockedUrlException('URL is empty or too long.', 'invalid_url');
        }
        if (preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
            throw new BlockedUrlException('URL contains whitespace, control characters or backslashes.', 'invalid_url');
        }

        // Check the scheme first so file:, gopher:, ftp: … are reported as such even without a host.
        if (preg_match('#^([a-z][a-z0-9+.-]*)://#i', $url, $m) && ! in_array(strtolower($m[1]), ['http', 'https'], true)) {
            throw new BlockedUrlException('Scheme "'.strtolower($m[1]).'" is not allowed.', 'scheme');
        }

        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            throw new BlockedUrlException('URL could not be parsed.', 'invalid_url');
        }

        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            throw new BlockedUrlException("Scheme \"{$scheme}\" is not allowed.", 'scheme');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new BlockedUrlException('URLs with credentials are not allowed.', 'credentials');
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        if (! in_array($port, $this->allowedPorts, true)) {
            throw new BlockedUrlException("Port {$port} is not allowed.", 'port');
        }

        $host = $this->normalizeHost($parts['host']);

        $path = $parts['path'] ?? '/';
        if ($path === '') {
            $path = '/';
        }
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';
        $defaultPort = ($scheme === 'https' && $port === 443) || ($scheme === 'http' && $port === 80);

        $normalized = $scheme.'://'.$host.($defaultPort ? '' : ':'.$port).$path.$query;

        return new SafeUrl($normalized, $scheme, $host, $port, $path.$query);
    }

    private function normalizeHost(string $host): string
    {
        $host = rtrim(strtolower($host), '.');

        if (str_starts_with($host, '[') || filter_var($host, FILTER_VALIDATE_IP) || preg_match('/^[0-9.]+$/', $host) || preg_match('/^0x/i', $host)) {
            throw new BlockedUrlException('IP-address hosts are not allowed; use the store\'s domain name.', 'ip_literal');
        }

        if (preg_match('/[^\x20-\x7e]/', $host)) {
            $ascii = idn_to_ascii($host, IDNA_DEFAULT | IDNA_NONTRANSITIONAL_TO_ASCII, INTL_IDNA_VARIANT_UTS46);
            if ($ascii === false) {
                throw new BlockedUrlException('Hostname is not a valid internationalized domain name.', 'invalid_host');
            }
            $host = $ascii;
        }

        if (strlen($host) > 253 || ! preg_match('/^(?=.{1,253}$)([a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)(\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)+$/', $host)) {
            throw new BlockedUrlException('Hostname must be a fully qualified public domain.', 'invalid_host');
        }

        $labels = explode('.', $host);
        $tld = end($labels);
        if (ctype_digit($tld)) {
            throw new BlockedUrlException('Hostname has a numeric top-level domain.', 'invalid_host');
        }
        foreach (self::BLOCKED_SUFFIXES as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.'.$suffix)) {
                throw new BlockedUrlException("Hosts under \".{$suffix}\" are not allowed.", 'private_host');
            }
        }

        return $host;
    }
}
