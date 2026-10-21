<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    // Bitcoin payments (BTCPay Server, Greenfield API). Secrets live only in .env.
    'btcpay' => [
        'url' => env('BTCPAY_URL'),
        'api_key' => env('BTCPAY_API_KEY'),
        'store_id' => env('BTCPAY_STORE_ID'),
        'webhook_secret' => env('BTCPAY_WEBHOOK_SECRET'),
        'confirmations' => (int) env('BTCPAY_CONFIRMATIONS', 1),      // D8: 1 confirmation
        'expiration_minutes' => (int) env('BTCPAY_INVOICE_EXPIRATION', 30),
    ],

    // Cloudflare Turnstile for public forms (disabled while empty).
    'turnstile' => [
        'site_key' => env('TURNSTILE_SITE_KEY'),
        'secret_key' => env('TURNSTILE_SECRET_KEY'),
    ],
];
