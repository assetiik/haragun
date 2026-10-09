<?php

return [

    /*
    |--------------------------------------------------------------------------
    | About the app (profile screen)
    |--------------------------------------------------------------------------
    */

    'about' => [
        'title' => 'О приложении',
        'version' => env('API_VERSION', '1.0.0'),
        'text' => env(
            'HARAGUN_ABOUT_TEXT',
            'Haragun — приложение для заказа кофе, еды и билетов на ледовую арену. Оформляйте заказы заранее и забирайте их без очереди.',
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Support contacts (profile screen)
    |--------------------------------------------------------------------------
    */

    'contacts' => [
        'title' => 'Контакты',
        'phone' => env('HARAGUN_SUPPORT_PHONE', '+7 700 000 00 00'),
        'phone_raw' => env('HARAGUN_SUPPORT_PHONE_RAW', '77000000000'),
        'email' => env('HARAGUN_SUPPORT_EMAIL', 'support@haragun.kz'),
        'address' => env('HARAGUN_SUPPORT_ADDRESS', 'Алматы'),
        'hours' => env('HARAGUN_SUPPORT_HOURS', 'Ежедневно 09:00–22:00'),
    ],

];
