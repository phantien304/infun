<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cluster review_tag — tag tự do gắn vào review.
 *
 *  - "Đóng gói đẹp", "Giao hàng nhanh", "Chất lượng tốt", "Đáng tiền", ...
 *  - 2 nguồn: auto-generated từ pipeline NLP (`is_auto_generated=1`) và manual
 *    do admin tạo (`is_auto_generated=0`).
 *  - User KHÔNG tự gõ tag ở form — chọn từ chip có sẵn (UX Shopee, chống tag rác).
 *
 *  - usage_count denormalize cho hot-tag (gallery "Tag được nhắc nhiều").
 *    Cập nhật qua observer attach/detach review_tag_pivot.
 *
 * Bảng:
 *   review_tag (id, code UNIQUE, usage_count, is_auto_generated, is_active, ts, del)
 *   review_tag_description (review_tag_id FK, language_code, name)
 *   review_tag_pivot (review_id, review_tag_id, PRIMARY KEY composite)
 *
 * pivot KHÔNG có id auto-inc — composite PK đủ. CASCADE 2 chiều khi xóa
 * review hoặc tag.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('review_tag')) {
            Schema::create('review_tag', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('code', 64)->unique();
                $t->unsignedInteger('usage_count')->default(0);
                $t->boolean('is_auto_generated')->default(false);
                $t->boolean('is_active')->default(true);
                $t->timestamps();
                $t->softDeletes();

                $t->index(['is_active', 'usage_count'], 'idx_tag_active_usage');
            });
        }

        if (! Schema::hasTable('review_tag_description')) {
            Schema::create('review_tag_description', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('review_tag_id');
                $t->char('language_code', 5);
                $t->string('name', 100);
                $t->timestamps();

                $t->unique(['review_tag_id', 'language_code'], 'uniq_tag_desc_locale');

                $t->foreign('review_tag_id', 'fk_tag_desc_tag')
                    ->references('id')->on('review_tag')
                    ->cascadeOnDelete();
            });
        }

        if (! Schema::hasTable('review_tag_pivot')) {
            Schema::create('review_tag_pivot', function (Blueprint $t) {
                $t->integer('review_id');
                $t->unsignedBigInteger('review_tag_id');
                $t->timestamp('created_at')->useCurrent();

                $t->primary(['review_id', 'review_tag_id']);
                $t->index('review_tag_id', 'idx_tag_pivot_tag');

                $t->foreign('review_id', 'fk_tag_pivot_review')
                    ->references('id')->on('review')
                    ->cascadeOnDelete();
                $t->foreign('review_tag_id', 'fk_tag_pivot_tag')
                    ->references('id')->on('review_tag')
                    ->cascadeOnDelete();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('review_tag_pivot');
        Schema::dropIfExists('review_tag_description');
        Schema::dropIfExists('review_tag');
    }
};
