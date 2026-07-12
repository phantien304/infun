<?php

/*
 * Override MỘT phần config barryvdh/laravel-debugbar (mergeConfigFrom là
 * shallow merge tầng 1: key khai ở đây thay NGUYÊN mảng vendor, key không
 * khai giữ default vendor — enabled/storage/options... vẫn từ vendor).
 *
 * Lý do (2026-07-11): /san-pham?per_page=200 OOM 128M tại
 * LaravelDebugbar::injectDebugbar — trang list 200 SP × card relations
 * hydrate hàng nghìn model; collector `models` giữ ref toàn bộ, `views`
 * log hàng trăm partial card, `cache` log mọi event (page cache + schema
 * cache bắn rất nhiều) → serialize hết vào HTML. Tắt các collector nặng,
 * giữ db/time/memory/exceptions/messages đủ dùng hằng ngày.
 * (DB collector tự giới hạn qua options soft_limit=100 / hard_limit=500
 * của vendor.) Bật lại từng cái khi cần bằng env DEBUGBAR_COLLECTORS_*.
 */

return [
    'collectors' => [
        'phpinfo'         => env('DEBUGBAR_COLLECTORS_PHPINFO', false),
        'messages'        => env('DEBUGBAR_COLLECTORS_MESSAGES', true),
        'time'            => env('DEBUGBAR_COLLECTORS_TIME', true),
        'memory'          => env('DEBUGBAR_COLLECTORS_MEMORY', true),
        'exceptions'      => env('DEBUGBAR_COLLECTORS_EXCEPTIONS', true),
        'log'             => env('DEBUGBAR_COLLECTORS_LOG', true),
        'db'              => env('DEBUGBAR_COLLECTORS_DB', true),
        'views'           => env('DEBUGBAR_COLLECTORS_VIEWS', false),     // NẶNG: trang list render hàng trăm partial
        'route'           => env('DEBUGBAR_COLLECTORS_ROUTE', false),
        'auth'            => env('DEBUGBAR_COLLECTORS_AUTH', false),
        'gate'            => env('DEBUGBAR_COLLECTORS_GATE', false),
        'session'         => env('DEBUGBAR_COLLECTORS_SESSION', false),
        'symfony_request' => env('DEBUGBAR_COLLECTORS_SYMFONY_REQUEST', true),
        'mail'            => env('DEBUGBAR_COLLECTORS_MAIL', true),
        'laravel'         => env('DEBUGBAR_COLLECTORS_LARAVEL', true),
        'events'          => env('DEBUGBAR_COLLECTORS_EVENTS', false),
        'logs'            => env('DEBUGBAR_COLLECTORS_LOGS', false),
        'config'          => env('DEBUGBAR_COLLECTORS_CONFIG', false),
        'cache'           => env('DEBUGBAR_COLLECTORS_CACHE', false),     // NẶNG: page cache + schema cache bắn rất nhiều event
        'models'          => env('DEBUGBAR_COLLECTORS_MODELS', false),    // NẶNG NHẤT: giữ ref mọi model hydrate (200 SP × relations)
        'livewire'        => env('DEBUGBAR_COLLECTORS_LIVEWIRE', false),  // không dùng Livewire
        'inertia'         => env('DEBUGBAR_COLLECTORS_INERTIA', false),   // không dùng Inertia
        'jobs'            => env('DEBUGBAR_COLLECTORS_JOBS', false),
        'pennant'         => env('DEBUGBAR_COLLECTORS_PENNANT', false),
        'http_client'     => env('DEBUGBAR_COLLECTORS_HTTP_CLIENT', true),
    ],
];
