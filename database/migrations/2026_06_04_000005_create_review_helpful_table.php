<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `review_helpful` — vote "Hữu ích" / "Không hữu ích" cho từng review.
 *
 * `vote_type` SIGNED TINYINT:
 *    1  = helpful
 *   -1  = unhelpful
 *    0  = withdrawn (user gỡ vote — soft delete dùng cột vote_type=0 thay vì
 *         deleted_at, đơn giản hơn cho replay vote_count)
 *
 * UNIQUE (review_id, user_id) → 1 user 1 vote per review. User đổi ý: UPDATE
 * vote_type chứ không INSERT mới.
 *
 * Visitor (chưa login): app layer fallback dùng IP làm "user_id ảo" (vd
 * user_id=0 + ip column). Anti-spam thực tế nên kèm rate-limit middleware
 * + captcha — DB không tự bảo vệ.
 *
 * Counter denormalize: review.helpful_count + review.unhelpful_count cập nhật
 * qua observer trên review_helpful save/update.
 *
 * Schema:
 *   review_helpful (
 *     id BIGINT PK,
 *     review_id INT FK CASCADE,
 *     user_id INT DEFAULT 0,
 *     vote_type TINYINT DEFAULT 1,           -- 1/-1/0
 *     ip VARCHAR(45) NULL,                   -- IPv6-ready
 *     timestamps,
 *
 *     UNIQUE (review_id, user_id)            -- 1 user 1 vote
 *     INDEX (review_id, vote_type)           -- "vote 'helpful' của review X"
 *   )
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('review_helpful')) {
            return;
        }

        Schema::create('review_helpful', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->integer('review_id');
            $t->integer('user_id')->default(0);
            $t->tinyInteger('vote_type')->default(1);
            $t->string('ip', 45)->nullable();
            $t->timestamps();

            $t->unique(['review_id', 'user_id'], 'uniq_helpful_review_user');
            $t->index(['review_id', 'vote_type'], 'idx_helpful_review_vote');

            $t->foreign('review_id', 'fk_helpful_review')
                ->references('id')->on('review')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_helpful');
    }
};
