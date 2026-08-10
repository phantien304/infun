<?php

return [
    'image_disk' => env('IMAGE_DISK', 'image'),

    'thumbnail_sizes' => [[300, 300], [400, 400], [147, 147], [1000, 1000], [100, 100], [50, 50], [800, 354], [635, 420], [312, 340], [500, 500], [370, 300]],

    'cf_resizing' => [
        'enabled' => (bool) env('CF_IMAGE_RESIZING', false),
        'base'    => env('CF_IMAGE_RESIZING_BASE', ''),
        'options' => env('CF_IMAGE_RESIZING_OPTIONS', 'fit=cover,format=auto,quality=82'),
    ],

];
