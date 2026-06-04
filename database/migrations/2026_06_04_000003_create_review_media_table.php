<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `review_media` — ảnh + video user đính kèm review.
 *
 * Gộp ảnh + video trong 1 bảng với cột `type` (discriminator) thay vì 2 bảng
 * tách. Lý do:
 *  - Cùng vòng đời (gắn review, sort_order chung, soft delete chung).
 *  - Blade gallery render lẫn lộn ảnh + video theo sort_order → đơn bảng dễ ORDER BY.
 *  - Polymorphic count denormalize vào review.media_count đơn giản (COUNT *).
 *
 * Storage: app upload qua MyStorage::resizeImage giống product_image. URL
 * lưu path relative (vd "review/{review_id}/abc.jpg"), không hardcode domain.
 *
 * Video:
 *  - `url` trỏ tới file storage cục bộ HOẶC URL embed (YouTube, Vimeo) nếu cho phép.
 *  - `thumbnail` = poster image cho video preview ở grid.
 *  - `duration` (giây) hiển thị badge "0:45" overlay.
 *
 * Quota: app layer enforce tối đa N ảnh + M video per review (vd 9 ảnh + 1 video
 * giống Shopee), không enforce ở DB.
 *
 * Schema:
 *   review_media (
 *     id BIGINT PK,
 *     review_id INT FK CASCADE,
 *     type VARCHAR(8),                    -- 'image' / 'video'
 *     url VARCHAR(500) NOT NULL,
 *     thumbnail VARCHAR(500) NULL,        -- poster cho video, NULL cho image
 *     mime VARCHAR(64) NULL,
 *     file_size INT UNSIGNED NULL,        -- bytes
 *     width INT UNSIGNED NULL,
 *     height INT UNSIGNED NULL,
 *     duration INT UNSIGNED NULL,         -- giây, video only
 *     sort_order INT UNSIGNED DEFAULT 0,
 *     is_active BOOLEAN DEFAULT 1,
 *     timestamps, deleted_at,
 *
 *     INDEX (review_id, type, sort_order)
 *   )
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('review_media')) {
            return;
        }

        Schema::create('review_media', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->integer('review_id');
            $t->string('type', 8)->default('image');
            $t->string('url', 500);
            $t->string('thumbnail', 500)->nullable();
            $t->string('mime', 64)->nullable();
            $t->unsignedInteger('file_size')->nullable();
            $t->unsignedInteger('width')->nullable();
            $t->unsignedInteger('height')->nullable();
            $t->unsignedInteger('duration')->nullable();
            $t->unsignedInteger('sort_order')->default(0);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->softDeletes();

            $t->index(['review_id', 'type', 'sort_order'], 'idx_media_review_type_sort');

            $t->foreign('review_id', 'fk_media_review')
                ->references('id')->on('review')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_media');
    }
};
