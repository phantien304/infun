<?php

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

        'index-settings' => [
            'products' => [
                'filterableAttributes' => [
                    'id',
                    'manufacturer_id',
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
            ],
        ],
    ],

    'algolia' => [
        'id'             => env('ALGOLIA_APP_ID', ''),
        'secret'         => env('ALGOLIA_SECRET', ''),
        'index-settings' => [],
    ],
];
