<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `review_criteria` — định nghĩa các tiêu chí đánh giá đa chiều (Shopee UX).
 * Mặc định seed 5 tiêu chí: chất lượng sản phẩm, đúng mô tả, dịch vụ shop,
 * đóng gói, vận chuyển. Admin có thể bật/tắt hoặc thêm tiêu chí riêng theo
 * category sau (vd category "Đồ ăn" thêm "Hương vị", "Định lượng").
 *
 * Cluster: `review_criteria` + `review_criteria_description` (i18n).
 *
 * KHÔNG tách `category_id` ở version này — mọi tiêu chí áp dụng toàn site.
 * Nếu cần category-specific sau, thêm `category_id NULL` + scope filter ở repo,
 * không phải tạo bảng pivot mới (over-engineering).
 *
 * Schema:
 *   review_criteria (
 *     id BIGINT PK,
 *     code VARCHAR(32) UNIQUE,      -- 'quality', 'description_match', 'service',
 *                                      'packaging', 'shipping'
 *     icon VARCHAR(64) NULL,        -- icon class hoặc URL
 *     sort_order INT UNSIGNED DEFAULT 0,
 *     is_required BOOLEAN DEFAULT 0, -- tiêu chí bắt buộc khi user submit form
 *     is_active BOOLEAN DEFAULT 1,
 *     timestamps, deleted_at
 *   )
 *
 *   review_criteria_description (
 *     id BIGINT PK,
 *     review_criteria_id BIGINT FK CASCADE,
 *     language_code CHAR(5),
 *     name VARCHAR(100) NOT NULL,
 *     hint VARCHAR(255) NULL,        -- tooltip cho user
 *     UNIQUE (review_criteria_id, language_code)
 *   )
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('review_criteria')) {
            Schema::create('review_criteria', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('code', 32)->unique();
                $t->string('icon', 64)->nullable();
                $t->unsignedInteger('sort_order')->default(0);
                $t->boolean('is_required')->default(false);
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->softDeletes();

                $t->index(['is_active', 'sort_order'], 'idx_criteria_active_sort');
            });
        }

        if (! Schema::hasTable('review_criteria_description')) {
            Schema::create('review_criteria_description', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('review_criteria_id');
                $t->char('language_code', 5);
                $t->string('name', 100);
                $t->string('hint', 255)->nullable();
                $t->timestamps();

                $t->unique(['review_criteria_id', 'language_code'], 'uniq_criteria_desc_locale');

                $t->foreign('review_criteria_id', 'fk_criteria_desc_criteria')
                    ->references('id')->on('review_criteria')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('review_criteria_description');
        Schema::dropIfExists('review_criteria');
    }
};
