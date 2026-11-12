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

    'otp' => [
        'static_code' => env('OTP_STATIC_CODE'),
        'message' => env('OTP_SMS_MESSAGE', 'Ваш код для входа в Haragun: {code}'),
    ],

    'mobizon' => [
        'api_key' => env('MOBIZON_API_KEY'),
        'base_url' => env('MOBIZON_BASE_URL', 'https://api.mobizon.kz'),
        'from' => env('MOBIZON_FROM', 'Haragun'),
        'connect_timeout' => 3,
        'timeout' => 10,
    ],

    /*
    | Ticket provider for ice-arena sessions: dss_soft (current) or rink_api (new).
    | Keep dss_soft until rink_api is verified on staging.
    */
    'ticket_provider' => env('TICKET_PROVIDER', 'dss_soft'),

    'dss_soft' => [
        'base_url' => env('DSS_SOFT_BASE_URL', 'https://api.dss-soft.kz'),
        'token' => env('DSS_SOFT_PARTNER_TOKEN'),
        'public_key' => env('DSS_SOFT_PUBLIC_KEY'),
        'public_key_path' => env('DSS_SOFT_PUBLIC_KEY_PATH'),
        'show_id' => env('DSS_SOFT_SHOW_ID', 195),
        'connect_timeout' => 3,
        'timeout' => 10,
        'entry_opens_minutes_before' => 60,
    ],

    'rink_api' => [
        'base_url' => env('RINK_API_BASE_URL', 'https://178-62-199-193.sslip.io/api/v1'),
        'api_key' => env('RINK_API_KEY'),
        'sale_kind' => env('RINK_API_SALE_KIND', 'online'),
        'connect_timeout' => 3,
        'timeout' => 15,
    ],

    'iiko' => [
        'base_url' => env('IIKO_BASE_URL', 'https://api-ru.iiko.services'),
        'api_login' => env('IIKO_API_LOGIN'),
        'token_cache_key' => env('IIKO_TOKEN_CACHE_KEY', 'iiko.access_token'),
        'token_ttl_seconds' => (int) env('IIKO_TOKEN_TTL_SECONDS', 3300),
        'connect_timeout' => 3,
        'timeout' => 15,
        'command_poll_attempts' => (int) env('IIKO_COMMAND_POLL_ATTEMPTS', 10),
        'command_poll_ms' => (int) env('IIKO_COMMAND_POLL_MS', 400),
    ],

    'tiptop_pay' => [
        'enabled' => (bool) env('TIPTOP_PAY_ENABLED', false),
        'base_url' => env('TIPTOP_PAY_BASE_URL', 'https://api.tiptoppay.kz'),
        'public_id' => env('TIPTOP_PAY_PUBLIC_ID'),
        'api_secret' => env('TIPTOP_PAY_API_SECRET'),
        'connect_timeout' => 3,
        'timeout' => 30,
    ],

    'fcm' => [
        'project_id' => env('FIREBASE_PROJECT_ID'),
        'credentials' => env('FIREBASE_CREDENTIALS'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

];
