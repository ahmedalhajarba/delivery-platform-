<?php

return [
    'enabled' => env('ICTCRM_ENABLED', false),
    'base_url' => env('ICTCRM_BASE_URL', ''),
    'api_key' => env('ICTCRM_API_KEY', ''),
    'webhook_token' => env('ICTCRM_WEBHOOK_TOKEN', ''),
    'require_webhook_token' => (bool) env('ICTCRM_REQUIRE_WEBHOOK_TOKEN', true),
    'timeout' => (int) env('ICTCRM_TIMEOUT', 15),

    'endpoints' => [
        'dial' => env('ICTCRM_ENDPOINT_DIAL', '/api/v1/calls/dial'),
        'transfer' => env('ICTCRM_ENDPOINT_TRANSFER', '/api/v1/calls/transfer'),
    ],
];
