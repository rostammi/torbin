<?php

return [
    'hsts' => [
        'enabled' => env('HSTS_ENABLED', true),
        'max_age' => (int) env('HSTS_MAX_AGE', 31_536_000),
        'include_subdomains' => env('HSTS_INCLUDE_SUBDOMAINS', false),
        'preload' => env('HSTS_PRELOAD', false),
    ],
];
