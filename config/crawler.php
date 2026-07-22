<?php

return [
    'user_agent' => env('CRAWLER_USER_AGENT', 'Mozilla/5.0 (compatible; TourCompareBot/1.0; +'.env('APP_URL').')'),
    'search_days' => (int) env('CRAWLER_SEARCH_DAYS', 30),
    'safarmarket_origin_id' => (int) env('SAFARMARKET_ORIGIN_ID', 19981),
    'trends_geo' => env('GOOGLE_TRENDS_GEO', 'IR'),
    'trends_feed_url' => env('GOOGLE_TRENDS_FEED_URL', 'https://trends.google.com/trending/rss'),
    'suggestions_limit' => (int) env('TOUR_SUGGESTIONS_LIMIT', 120),
    'providers' => [
        ['name' => 'علی‌بابا', 'type' => 'alibaba', 'url' => 'https://www.alibaba.ir/tour'],
        ['name' => 'فلای‌تودی', 'type' => 'flytoday', 'url' => 'https://www.flytoday.ir/packagetour'],
        ['name' => 'سفرمارکت', 'type' => 'safarmarket', 'url' => 'https://safarmarket.com/tours'],
        ['name' => 'اقامت ۲۴', 'type' => 'marketplace_html', 'url' => 'https://www.eghamat24.com/Tours.html'],
        ['name' => 'لحظه آخر', 'type' => 'marketplace_html', 'url' => 'https://lahzeakhar.com/tour'],
        ['name' => 'لست‌سکند', 'type' => 'marketplace_html', 'url' => 'https://lastsecond.ir/tours'],
        ['name' => 'نهال‌گشت', 'type' => 'marketplace_html', 'url' => 'https://nahalgasht.com/tours/'],
        ['name' => 'مقتدر سیر', 'type' => 'marketplace_html', 'url' => 'https://mstiran.com/'],
        ['name' => 'جیمبو', 'type' => 'marketplace_html', 'url' => 'https://www.jimbo.ir/tours/'],
        ['name' => 'امین', 'type' => 'marketplace_html', 'url' => 'https://amenin.co/'],
    ],
];
