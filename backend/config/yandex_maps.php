<?php

return [
    'allowed_hosts' => [
        'yandex.ru',
        'yandex.com',
    ],

    'connect_timeout' => (int) env('YANDEX_MAPS_CONNECT_TIMEOUT', 5),
    'timeout' => (int) env('YANDEX_MAPS_TIMEOUT', 20),
    'retries' => (int) env('YANDEX_MAPS_RETRIES', 2),
    'retry_delay_ms' => (int) env('YANDEX_MAPS_RETRY_DELAY_MS', 500),
    'max_pages' => (int) env('YANDEX_MAPS_MAX_PAGES', 100),

    'user_agent' => env(
        'YANDEX_MAPS_USER_AGENT',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 Chrome/140 Safari/537.36',
    ),
];
