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
        ['name' => 'اقامت ۲۴', 'type' => 'structured', 'url' => 'https://www.eghamat24.com/tour'],
        ['name' => 'لحظه آخر', 'type' => 'structured', 'url' => 'https://lahzeakhar.com/tour'],
        ['name' => 'لست‌سکند', 'type' => 'structured', 'url' => 'https://lastsecond.ir/tours'],
        ['name' => 'تورگردان', 'type' => 'structured', 'url' => 'https://tourgardan.com/tours'],
        ['name' => 'نهال‌گشت', 'type' => 'structured', 'url' => 'https://nahalgasht.com/tours'],
        ['name' => 'الفبای سفر', 'type' => 'structured', 'url' => 'https://www.alefbatour.com/tour'],
        ['name' => 'آریاک سفر', 'type' => 'structured', 'url' => 'https://www.aryaktravel.com/tour'],
    ],
];
