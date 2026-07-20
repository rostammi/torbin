<?php

return [
    'user_agent' => env('CRAWLER_USER_AGENT', 'Mozilla/5.0 (compatible; TourCompareBot/1.0; +'.env('APP_URL').')'),
    'search_days' => (int) env('CRAWLER_SEARCH_DAYS', 30),
    'safarmarket_origin_id' => (int) env('SAFARMARKET_ORIGIN_ID', 19981),
];
