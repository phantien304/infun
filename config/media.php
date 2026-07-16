<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Disk phục vụ ảnh
    |--------------------------------------------------------------------------
    | thumbnail() và ProcessImageUpload đọc/ghi ảnh + thumbnail qua disk này.
    | Prod: 'image' (R2 khi IMAGE_DISK_DRIVER=s3). Tạm về 'public' nếu muốn
    | chạy local trước khi migrate.
    */
    'image_disk' => env('IMAGE_DISK', 'image'),

    /*
    |--------------------------------------------------------------------------
    | Các size thumbnail sinh sẵn
    |--------------------------------------------------------------------------
    | Job ProcessImageUpload sinh sẵn đúng các size này trên R2 để trang list
    | KHÔNG phải resize lúc render. 300x300 = card trang list (_product.blade).
    */
    'thumbnail_sizes' => [
        [300, 300],   // card trang list (_product.blade)
        [147, 147],   // thumb gallery trang chi tiết
        [1000, 1000], // ảnh lớn trang chi tiết
        [100, 100], // ảnh thumb trang giỏ hàng
        [50, 50],     // sidebar
        [800, 354],   // og:image / social
    ],

];
