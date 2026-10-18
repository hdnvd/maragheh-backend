<?php

return [
    'admin_url' => env('ADMIN_URL', 'https://admin.maraghecity.ir'),

    'otp' => [
        'length' => (int) env('OTP_LENGTH', 5),
        'ttl' => (int) env('OTP_TTL_SECONDS', 180),
        'resend' => (int) env('OTP_RESEND_SECONDS', 120),
        'max_attempts' => 5,
    ],

    'kavenegar' => [
        'api_key' => env('KAVENEGAR_API_KEY'),
        'template' => env('KAVENEGAR_TEMPLATE', 'maragheh-otp'),
    ],

    'weather' => [
        'lat' => (float) env('WEATHER_LAT', 37.3917),
        'lon' => (float) env('WEATHER_LON', 46.2392),
        'cache_minutes' => (int) env('WEATHER_CACHE_MINUTES', 30),
    ],

    'crawler' => [
        'user_agent' => env('CRAWLER_USER_AGENT', 'Mozilla/5.0 (compatible; MaraghehCityBot/1.0)'),
        'max_articles' => (int) env('CRAWLER_MAX_ARTICLES_PER_RUN', 40),
        'store_full_body' => (bool) env('NEWS_STORE_FULL_BODY', true),
    ],

    'uploads' => [
        'max_kb' => 12288,
        'max_side' => 2048,
        'thumb_side' => 480,
    ],
];
