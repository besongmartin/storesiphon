<?php

/*
| Safe fetcher settings (app/Domain/Fetching). Every outbound request StoreSiphon makes to a
| store goes through SafeHttpClient, which enforces these limits.
*/

return [

    // Identifiable user agent; robots.txt groups are matched on the product token.
    'user_agent' => env('FETCH_USER_AGENT', 'StoreSiphonBot/1.0 (+https://storesiphon.com/bot)'),
    'robots_agent' => 'StoreSiphonBot',

    'connect_timeout' => (float) env('FETCH_CONNECT_TIMEOUT', 10),
    'timeout' => (float) env('FETCH_TIMEOUT', 30),

    // Responses larger than this are aborted (bytes).
    'max_response_bytes' => (int) env('FETCH_MAX_RESPONSE_BYTES', 10 * 1024 * 1024),

    'max_redirects' => 5,

    // Only standard web ports are allowed.
    'allowed_ports' => [80, 443],

    // Politeness per store host.
    'min_interval_seconds' => (float) env('FETCH_MIN_INTERVAL', 1.0),
    'max_crawl_delay_seconds' => 60.0,       // a robots.txt Crawl-delay above this is capped (and logged)
    'max_concurrency_per_host' => 2,

    // Retries for 429 / 5xx / timeouts.
    'max_attempts' => 3,
    'backoff_base_seconds' => 2.0,
    'max_retry_after_seconds' => 120,        // a longer Retry-After ends the request with RetryLaterException

    'robots_cache_ttl' => 60 * 60 * 24,
];
