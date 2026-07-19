<?php

return [
    'image_disk' => env('IMAGE_DISK', 'image'),

    'thumbnail_sizes' => [
        [300, 300],   // card trang list (_product.blade)
        [147, 147],   // thumb gallery trang chi tiết
        [1000, 1000], // ảnh lớn trang chi tiết
        [100, 100], // ảnh thumb trang giỏ hàng
        [50, 50],     // sidebar
        [800, 354],   // og:image / social
    ],

    'cf_resizing' => [
        'enabled' => (bool) env('CF_IMAGE_RESIZING', false),
        'base'    => env('CF_IMAGE_RESIZING_BASE', ''),
        'options' => env('CF_IMAGE_RESIZING_OPTIONS', 'fit=cover,format=auto,quality=82'),
    ],

];
