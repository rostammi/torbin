<?php

return [
    'sitemap' => [
        'path' => env('SITEMAP_PATH', public_path('sitemap.xml')),
        'auto_refresh' => (bool) env('SITEMAP_AUTO_REFRESH', true),
    ],
];
