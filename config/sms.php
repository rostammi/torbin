<?php

return [
    'driver' => env('SMS_DRIVER', 'log'),

    'kavenegar' => [
        'api_key' => env('KAVENEGAR_API_KEY'),
        'sender' => env('KAVENEGAR_SENDER'),
    ],

    'webhook' => [
        'url' => env('SMS_WEBHOOK_URL'),
        'token' => env('SMS_WEBHOOK_TOKEN'),
    ],
];
