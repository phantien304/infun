<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `review_rating` — bảng pivot lưu rating từng tiêu chí của 1 review.
 *
 * Composite PK (review_id, review_criteria_id) cưỡng chế "1 review × 1 tiêu chí
 * → đúng 1 rating" ở DB level. App layer KHÔNG cần validate trùng.
 *
 * Cột `review.rating` (overall, 1-5) vẫn được giữ ở bảng review:
 *  - Hiển thị nhanh badge "4.5★" ngoài card không cần JOIN.
 *  - Tính bằng AVG(review_rating.rating) nếu user chấm đa tiêu chí, hoặc
 *    bằng chính rating user nhập nếu form chỉ có 1 trường (form đơn giản).
 *  - Observer trên review_rating sau insert/update sẽ cập nhật review.rating.
 *
 * Schema:
 *   review_rating (
 *     review_id INT,
 *     review_criteria_id BIGINT,
 *     rating TINYINT UNSIGNED,         -- 1-5
 *     PRIMARY KEY (review_id, review_criteria_id),
 *     FK review_id → review.id            CASCADE
 *     FK review_criteria_id → review_criteria.id  RESTRICT
 *       -- RESTRICT vì xóa criteria khi đã có rating sẽ mất context;
 *       --  bắt admin migrate trước (set is_active=0).
 *   )
 *
 *   INDEX (review_criteria_id, rating) — aggregate "avg rating per criterion per product".
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('review_rating')) {
            return;
        }

        Schema::create('review_rating', function (Blueprint $t) {
            $t->integer('review_id');
            $t->unsignedBigInteger('review_criteria_id');
            $t->unsignedTinyInteger('rating');
            $t->timestamps();

            $t->primary(['review_id', 'review_criteria_id']);
            $t->index(['review_criteria_id', 'rating'], 'idx_rating_criteria_rating');

            $t->foreign('review_id', 'fk_rating_review')
                ->references('id')->on('review')
                ->cascadeOnDelete();
            $t->foreign('review_criteria_id', 'fk_rating_criteria')
                ->references('id')->on('review_criteria')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_rating');
    }
};
