<?php

/*
| Product limits. Until paid plans exist (Phase 4) every account uses the Free limits.
*/

return [

    'limits' => [
        'free' => [
            'max_products_per_job' => (int) env('FREE_MAX_PRODUCTS', 25),
            'concurrent_jobs' => 1,
            'retention_days' => 7,
        ],
        'guest' => [
            'max_products_per_job' => 10,   // anonymous "try it" preview, no export
            'jobs_per_hour_per_ip' => 3,
            'retention_days' => 1,
        ],
    ],

    'exports' => [
        'disk' => env('EXPORTS_DISK', 'local'),
        'formats' => ['csv', 'xlsx', 'json'],
        'link_ttl_minutes' => 60,
    ],

    // Queue used for extraction work (kept separate from mail/notifications).
    'queue' => env('EXTRACTION_QUEUE', 'extractions'),
];
