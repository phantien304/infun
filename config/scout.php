<?php

/*
|--------------------------------------------------------------------------
| Index per-locale — products_vi, products_en, ...
|--------------------------------------------------------------------------
| `Product::searchableAs()` = 'products_'.locale (description dịch theo
| locale → 1 index chung là sai cho multilingual). Settings giống hệt nhau
| cho mọi locale, build từ APP_LOCALES.
|
| CỐ Ý đọc thẳng env thay vì config('app.locales') — file config load độc
| lập, không dựa config khác. CŨNG CỐ Ý KHÔNG `new Product` để lấy
| searchableAs() — config load TRƯỚC service provider boot, instantiate
| model trigger trait Searchable + Auditable cần `view` service chưa
| register → ReflectionException: Class "view" does not exist.
| (`scout:sync-index-settings` tự prepend SCOUT_PREFIX cho key thường.)
*/

$scoutProductLocales = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) env('APP_LOCALES', 'vi'))
)));

$scoutProductIndexSettings = [
    'filterableAttributes' => [
        'id',
        'manufacturer_id',
        'category_id',
        'filter_value_id',
        'has_variants',
        'min_variant_price',
        'max_variant_price',
        'max_variant_discount_percent',
        'viewed',
        'rating_avg',
        'created_at',
    ],
    'sortableAttributes' => [
        'created_at',
        'sort_order',
        'name',
        'min_variant_price',
        'max_variant_price',
        'max_variant_discount_percent',
        'viewed',
        'rating_avg',
    ],
    'searchableAttributes' => [
        'name',
        'sku',
        'model',
        'description',
    ],
];

$scoutProductIndexes = [];
foreach ($scoutProductLocales as $scoutLocale) {
    $scoutProductIndexes['products_' . $scoutLocale] = $scoutProductIndexSettings;
}

return [

    /*
    |--------------------------------------------------------------------------
    | Default Search Engine
    |--------------------------------------------------------------------------
    | Supported: "algolia", "meilisearch", "typesense",
    |            "database", "collection", "null"
    */
    'driver' => env('SCOUT_DRIVER', 'meilisearch'),

    'prefix' => env('SCOUT_PREFIX', ''),

    'queue' => env('SCOUT_QUEUE', false),

    'after_commit' => false,

    'chunk' => [
        'searchable'   => (int) env('SCOUT_CHUNK_SEARCHABLE', 500),
        'unsearchable' => (int) env('SCOUT_CHUNK_UNSEARCHABLE', 500),
    ],

    'soft_delete' => false,

    'identify' => env('SCOUT_IDENTIFY', false),

    'meilisearch' => [
        'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
        'key'  => env('MEILISEARCH_KEY'),

        'index-settings' => $scoutProductIndexes,
    ],

    'algolia' => [
        'id'             => env('ALGOLIA_APP_ID', ''),
        'secret'         => env('ALGOLIA_SECRET', ''),
        'index-settings' => [],
    ],
];
