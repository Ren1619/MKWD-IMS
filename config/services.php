<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
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

    'hris' => [
        'url' => env('HRIS_API_URL', env('HRIS_API_BASE_URL')),
        'allowed_hosts' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('HRIS_API_ALLOWED_HOSTS', '')),
        ))),
        'token' => env('HRIS_API_TOKEN'),
        'employees_path' => env('HRIS_API_EMPLOYEES_PATH', '/api/v1/inventory/employees'),
        'reference_data_path' => env('HRIS_API_REFERENCE_DATA_PATH', '/api/v1/inventory/reference-data'),
        'connect_timeout' => (int) env('HRIS_API_CONNECT_TIMEOUT', 3),
        'timeout' => (int) env('HRIS_API_TIMEOUT', 15),
        'auth_enabled' => (bool) env('HRIS_AUTH_ENABLED', false),
        'auth_token' => env('HRIS_AUTH_API_TOKEN') ?: env('HRIS_API_TOKEN'),
        'auth_path' => env('HRIS_AUTH_API_PATH', '/api/v1/auth/verify'),
        'auth_connect_timeout' => (int) env('HRIS_AUTH_CONNECT_TIMEOUT', 3),
        'auth_timeout' => (int) env('HRIS_AUTH_TIMEOUT', 8),
        'development_fallback_enabled' => (bool) env('HRIS_AUTH_DEVELOPMENT_FALLBACK', false),
    ],

];
