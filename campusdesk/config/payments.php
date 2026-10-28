<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Payment Gateway Driver
    |--------------------------------------------------------------------------
    |
    | Supported: "fake", "fapshi", "notchpay"
    |
    */
    'default' => env('PAYMENT_PROVIDER', 'fake'),

    'currency' => 'XAF',

    'gateways' => [
        'fake' => [
            'driver' => 'fake',
            'auto_succeed' => env('FAKE_PAYMENT_AUTO_SUCCEED', false),
        ],

        'fapshi' => [
            'driver' => 'fapshi',
            'api_user' => env('FAPSHI_API_USER'),
            'api_key' => env('FAPSHI_API_KEY'),
            'base_url' => env('FAPSHI_BASE_URL', 'https://sandbox.fapshi.com'),
            'webhook_secret' => env('FAPSHI_WEBHOOK_SECRET'),
        ],

        'notchpay' => [
            'driver' => 'notchpay',
            'public_key' => env('NOTCH_PUBLIC_KEY'),
            'private_key' => env('NOTCH_PRIVATE_KEY'),
            'base_url' => env('NOTCH_BASE_URL', 'https://api.notchpay.co'),
            'webhook_secret' => env('NOTCH_WEBHOOK_SECRET'),
        ],
    ],
];
