<?php

namespace App\Domain\Fetching;

use App\Domain\Fetching\Dns\DnsResolver;
use App\Domain\Fetching\Exceptions\AccessDeniedException;
use App\Domain\Fetching\Exceptions\BlockedUrlException;
use App\Domain\Fetching\Exceptions\ChallengeDetectedException;
use App\Domain\Fetching\Exceptions\FetchFailedException;
use App\Domain\Fetching\Exceptions\ResponseTooLargeException;
use App\Domain\Fetching\Exceptions\RetryLaterException;
use App\Domain\Fetching\Exceptions\RobotsDisallowedException;
use App\Domain\Fetching\Exceptions\TooManyRedirectsException;
use App\Domain\Fetching\Robots\RobotsPolicy;
use App\Domain\Fetching\Robots\RobotsTxt;
use GuzzleHttp\TransferStats;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * The only way StoreSiphon talks to the internet.
 *
 * Per hop: UrlGuard → robots.txt → DNS (every answer must be public) → connection pinned to the vetted
 * IP (and verified afterwards) → manual redirect handling with re-validation → size cap → retries with
 * backoff that honour Retry-After → hard stops on access controls and bot challenges.
 */
final class SafeHttpClient
{
    /** Strong markers of bot-protection interstitials. Deliberately narrow to avoid false positives. */
    private const CHALLENGE_MARKERS = [
        '/cdn-cgi/challenge-platform/',
        'cf_chl_opt',
        '<title>Just a moment...</title>',
        'Attention Required! | Cloudflare',
        '_Incapsula_Resource',
        'sucuri_cloudproxy_js',
        'px-captcha',
        'DDoS protection by DDoS-Guard',
    ];

    public function __construct(
        private readonly HttpFactory $http,
        private readonly DnsResolver $dns,
        private readonly UrlGuard $guard,
        private readonly HostThrottle $throttle,
        private readonly RobotsPolicy $robots,
        private readonly array $config,
    ) {}

    /**
     * GET a URL on behalf of an extraction. Throws a FetchException subclass on any policy stop or
     * persistent failure; returns non-2xx responses such as 404 for the caller to interpret.
     *
     * @param  array<string, string>  $headers
     */
    public function get(string $url, array $headers = []): FetchResponse
    {
        $current = $this->guard->check($url);
        $redirects = 0;

        while (true) {
            $robots = $this->robotsFor($current);
            if (! $robots->isAllowed($current->pathAndQuery)) {
                throw new RobotsDisallowedException("robots.txt disallows {$current->url}");
            }

            $response = $this->attempt($current, $headers, $this->crawlDelay($robots));

            if (! $this->isRedirect($response)) {
                return new FetchResponse($response->status, $response->headers, $response->body, $current->url, $response->ip, $redirects);
            }

            if (++$redirects > $this->config['max_redirects']) {
                throw new TooManyRedirectsException("More than {$this->config['max_redirects']} redirects starting at {$url}");
            }
            $current = $this->guard->check($current->resolve((string) $response->header('location')));
        }
    }

    /** Parsed robots.txt for a URL's origin (cached). Also used by the extraction engine to find sitemaps. */
    public function robotsFor(SafeUrl $url): RobotsTxt
    {
        return $this->robots->for($url, function (SafeUrl $robotsUrl): ?FetchResponse {
            try {
                return $this->fetchRobotsFile($robotsUrl);
            } catch (BlockedUrlException $e) {
                throw $e;
            } catch (Throwable $e) {
                Log::info('robots.txt unreachable', ['url' => $robotsUrl->url, 'error' => $e->getMessage()]);

                return null;
            }
        });
    }

    /** robots.txt itself: same network safety, its own redirects (≤5, same checks), no robots check. */
    private function fetchRobotsFile(SafeUrl $url): FetchResponse
    {
        $current = $url;
        for ($hop = 0; $hop <= $this->config['max_redirects']; $hop++) {
            $response = $this->attempt($current, ['Accept' => 'text/plain,*/*;q=0.5'], 0.0, robots: true);
            if (! $this->isRedirect($response)) {
                return $response;
            }
            $current = $this->guard->check($current->resolve((string) $response->header('location')));
        }

        throw new TooManyRedirectsException('robots.txt redirected too many times');
    }

    /** One logical request with retries (no redirect following). */
    private function attempt(SafeUrl $url, array $headers, float $crawlDelay, bool $robots = false): FetchResponse
    {
        $maxAttempts = max(1, (int) $this->config['max_attempts']);

        for ($attempt = 1; ; $attempt++) {
            $last = $attempt >= $maxAttempts;

            try {
                $response = $this->throttle->run($url->host, $crawlDelay, fn () => $this->sendOnce($url, $headers));
            } catch (ConnectionException $e) {
                if ($last) {
                    throw new FetchFailedException("Could not connect to {$url->host}: {$e->getMessage()}", 0, $e);
                }
                $this->backoff($attempt);

                continue;
            }

            if (! $robots) {
                $this->guardAgainstChallenges($response);
            }

            $status = $response->status;

            if (! $robots && ($status === 401 || $status === 403)) {
                throw new AccessDeniedException("{$url->host} answered {$status} for {$url->url}");
            }

            if ($status === 429 || $status === 503) {
                $retryAfter = $this->retryAfterSeconds($response->header('retry-after'));
                if ($retryAfter !== null && $retryAfter > $this->config['max_retry_after_seconds']) {
                    throw new RetryLaterException("{$url->host} asked us to wait {$retryAfter}s", $retryAfter);
                }
                if ($last) {
                    if ($status === 429) {
                        throw new RetryLaterException("{$url->host} is rate limiting us", $retryAfter ?? 60);
                    }
                    if ($robots) {
                        return $response;
                    }
                    throw new FetchFailedException("{$url->host} is unavailable (503)");
                }
                $retryAfter !== null ? Sleep::for($retryAfter)->seconds() : $this->backoff($attempt);

                continue;
            }

            if ($status >= 500) {
                if ($last) {
                    if ($robots) {
                        return $response;
                    }
                    throw new FetchFailedException("{$url->host} answered {$status} after {$attempt} attempts");
                }
                $this->backoff($attempt);

                continue;
            }

            return $response;
        }
    }

    /** A single HTTP exchange with DNS vetting and IP pinning. */
    private function sendOnce(SafeUrl $url, array $headers): FetchResponse
    {
        $ips = $this->dns->resolve($url->host);
        if ($ips === []) {
            throw new FetchFailedException("{$url->host} does not resolve");
        }
        foreach ($ips as $ip) {
            if (! IpPolicy::isPublic($ip)) {
                throw new BlockedUrlException("{$url->host} resolves to a non-public address", 'private_address');
            }
        }
        $pinned = $this->preferIpv4($ips);

        $max = (int) $this->config['max_response_bytes'];
        $tooLarge = false;
        $connectedIp = null;

        try {
            $response = $this->http
                ->withHeaders(array_merge([
                    'User-Agent' => $this->config['user_agent'],
                    'Accept' => 'application/json, text/html;q=0.9, application/xml;q=0.8, */*;q=0.5',
                    'Accept-Language' => 'en',
                ], $headers))
                ->withOptions([
                    'allow_redirects' => false,
                    'connect_timeout' => $this->config['connect_timeout'],
                    'timeout' => $this->config['timeout'],
                    'proxy' => '',
                    'cookies' => false,
                    'decode_content' => true,
                    'curl' => [
                        CURLOPT_RESOLVE => [self::pinEntry($url->host, $url->port, $pinned)],
                        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                    ],
                    'on_headers' => function (ResponseInterface $r) use ($max, &$tooLarge) {
                        if ((int) $r->getHeaderLine('Content-Length') > $max) {
                            $tooLarge = true;
                            throw new \RuntimeException('response too large');
                        }
                    },
                    'progress' => function ($downloadTotal, $downloaded) use ($max, &$tooLarge) {
                        if ($downloaded > $max) {
                            $tooLarge = true;
                            throw new \RuntimeException('response too large');
                        }
                    },
                    'on_stats' => function (TransferStats $stats) use (&$connectedIp) {
                        $connectedIp = $stats->getHandlerStat('primary_ip') ?: null;
                    },
                ])
                ->get($url->url);
        } catch (ConnectionException|RequestException $e) {
            if ($tooLarge) {
                throw new ResponseTooLargeException("Response from {$url->host} exceeded {$max} bytes");
            }
            throw $e instanceof ConnectionException ? $e : new ConnectionException($e->getMessage(), 0, $e);
        }

        if ($tooLarge || strlen($response->body()) > $max) {
            throw new ResponseTooLargeException("Response from {$url->host} exceeded {$max} bytes");
        }

        // Defence in depth: the socket must have gone to the address we vetted.
        if ($connectedIp !== null && @inet_pton($connectedIp) !== @inet_pton($pinned)) {
            throw new BlockedUrlException("Connection to {$url->host} did not use the vetted address", 'pin_mismatch');
        }

        $headersOut = [];
        foreach ($response->headers() as $name => $values) {
            $headersOut[strtolower($name)] = array_values((array) $values);
        }

        return new FetchResponse($response->status(), $headersOut, $response->body(), $url->url, $connectedIp ?? $pinned);
    }

    /** CURLOPT_RESOLVE entry pinning host:port to an address. */
    public static function pinEntry(string $host, int $port, string $ip): string
    {
        return $host.':'.$port.':'.(str_contains($ip, ':') ? '['.$ip.']' : $ip);
    }

    private function guardAgainstChallenges(FetchResponse $response): void
    {
        if (strtolower((string) $response->header('cf-mitigated')) === 'challenge') {
            throw new ChallengeDetectedException("{$response->url} is behind a bot challenge");
        }
        // Interstitials are small HTML pages; skip large bodies and non-HTML content.
        if (strlen($response->body) > 200_000 || ! str_contains((string) $response->header('content-type'), 'html')) {
            return;
        }
        foreach (self::CHALLENGE_MARKERS as $marker) {
            if (str_contains($response->body, $marker)) {
                throw new ChallengeDetectedException("{$response->url} served a bot challenge page");
            }
        }
    }

    private function isRedirect(FetchResponse $response): bool
    {
        return in_array($response->status, [301, 302, 303, 307, 308], true) && $response->header('location');
    }

    private function crawlDelay(RobotsTxt $robots): float
    {
        $delay = $robots->crawlDelay ?? 0.0;
        $cap = (float) $this->config['max_crawl_delay_seconds'];
        if ($delay > $cap) {
            Log::info('Crawl-delay capped', ['requested' => $delay, 'cap' => $cap]);

            return $cap;
        }

        return $delay;
    }

    private function retryAfterSeconds(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $value = trim($value);
        if (ctype_digit($value)) {
            return (int) $value;
        }
        try {
            return max(0, (int) ceil(Carbon::parse($value)->getTimestamp() - Carbon::now()->getTimestamp()));
        } catch (Throwable) {
            return null;
        }
    }

    private function backoff(int $attempt): void
    {
        $seconds = (float) $this->config['backoff_base_seconds'] * (2 ** ($attempt - 1));
        $jitterMs = random_int(0, 500);
        Sleep::for((int) ($seconds * 1000) + $jitterMs)->milliseconds();
    }

    /** @param list<string> $ips */
    private function preferIpv4(array $ips): string
    {
        foreach ($ips as $ip) {
            if (! str_contains($ip, ':')) {
                return $ip;
            }
        }

        return $ips[0];
    }
}
