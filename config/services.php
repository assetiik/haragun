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
        'static_code' => env('OTP_STATIC_CODE', '1234'),
    ],

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
