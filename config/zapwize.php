<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Zapwize API Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your Zapwize API settings. You can get your
    | API key from your Zapwize dashboard.
    |
    */

    'api_key' => env('ZAPWIZE_API_KEY'),

    'base_url' => env('ZAPWIZE_BASE_URL', 'https://api.zapwize.com/v1'),

    /*
    |--------------------------------------------------------------------------
    | Connection Settings
    |--------------------------------------------------------------------------
    |
    | Configure connection timeouts and retry attempts for the HTTP client
    | and WebSocket connections.
    |
    */

    'timeout' => env('ZAPWIZE_TIMEOUT', 30),

    'max_reconnect_attempts' => env('ZAPWIZE_MAX_RECONNECT_ATTEMPTS', 10),

    'reconnect_delay' => env('ZAPWIZE_RECONNECT_DELAY', 5000),

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Configure logging for Zapwize operations.
    |
    */

    'log_channel' => env('ZAPWIZE_LOG_CHANNEL', 'default'),

    'log_level' => env('ZAPWIZE_LOG_LEVEL', 'info'),

    /*
    |--------------------------------------------------------------------------
    | Queue Settings
    |--------------------------------------------------------------------------
    |
    | Configure queue settings for asynchronous message processing.
    |
    */

    'queue' => [
        'connection' => env('ZAPWIZE_QUEUE_CONNECTION', 'default'),
        'queue' => env('ZAPWIZE_QUEUE', 'zapwize'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Webhook Settings
    |--------------------------------------------------------------------------
    |
    | Configure webhook settings for incoming message handling.
    |
    */

    'webhook' => [
        'enabled' => env('ZAPWIZE_WEBHOOK_ENABLED', false),
        'url' => env('ZAPWIZE_WEBHOOK_URL'),
        'secret' => env('ZAPWIZE_WEBHOOK_SECRET'),
    ],
];