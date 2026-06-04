<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `review_reply` — phản hồi (shop reply / nested reply user khác).
 *
 * Self-referencing `parent_reply_id` để hỗ trợ nested reply (user khác reply
 * vào reply của shop). Depth giới hạn ở app layer (vd max 2 cấp giống Shopee:
 * shop reply → user reply tiếp). KHÔNG dùng nested set / closure table cho
 * phiên bản này — over-engineering với depth thấp.
 *
 * `author_type` discriminator:
 *   0 = customer (user thường)
 *   1 = shop (admin shop / seller account)
 *   2 = admin (super-admin moderate)
 *
 * Phân biệt với `user_id` (chính chủ tài khoản) — author_type quyết định
 * cách hiển thị badge ("Phản hồi của Shop"), không phải permission.
 *
 * Counter: review.reply_count cập nhật qua observer save/delete.
 *
 * Schema:
 *   review_reply (
 *     id BIGINT PK,
 *     review_id INT FK CASCADE,
 *     parent_reply_id BIGINT NULL FK self CASCADE,
 *     user_id INT NOT NULL,                    -- không FK strict (visitor reply không có user_id)
 *     author_type TINYINT UNSIGNED DEFAULT 0,  -- 0=customer 1=shop 2=admin
 *     text TEXT NOT NULL,
 *     is_publish BOOLEAN DEFAULT 1,
 *     timestamps, deleted_at,
 *
 *     INDEX (review_id, created_at)
 *     INDEX (parent_reply_id)
 *     INDEX (user_id)
 *   )
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('review_reply')) {
            return;
        }

        Schema::create('review_reply', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->integer('review_id');
            $t->unsignedBigInteger('parent_reply_id')->nullable();
            $t->integer('user_id')->default(0);
            $t->unsignedTinyInteger('author_type')->default(0);
            $t->text('text');
            $t->boolean('is_publish')->default(true);
            $t->timestamps();
            $t->softDeletes();

            $t->index(['review_id', 'created_at'], 'idx_reply_review_created');
            $t->index('parent_reply_id', 'idx_reply_parent');
            $t->index('user_id', 'idx_reply_user');

            $t->foreign('review_id', 'fk_reply_review')
                ->references('id')->on('review')
                ->cascadeOnDelete();
            $t->foreign('parent_reply_id', 'fk_reply_parent')
                ->references('id')->on('review_reply')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_reply');
    }
};
