<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `review_report` — user flag review (spam / xúc phạm / fake / không liên quan).
 *
 * Admin queue:
 *   - SELECT WHERE status = 0 ORDER BY created_at — danh sách chờ xử lý.
 *   - Resolve: status=1 (action taken — hide/delete review), 2=rejected (false alarm).
 *
 * Anti-abuse: UNIQUE (review_id, reported_by) chống user spam report 1 review.
 *
 * Schema:
 *   review_report (
 *     id BIGINT PK,
 *     review_id INT FK CASCADE,
 *     reported_by INT NOT NULL,             -- user.id (visitor không report được)
 *     reason_code VARCHAR(32),              -- 'spam' / 'offensive' / 'fake' / 'irrelevant' / 'other'
 *     description TEXT NULL,
 *     status TINYINT UNSIGNED DEFAULT 0,    -- 0=pending 1=resolved 2=rejected
 *     resolved_by INT NULL,                 -- admin user.id
 *     resolved_at TIMESTAMP NULL,
 *     resolution_note TEXT NULL,
 *     timestamps,
 *
 *     UNIQUE (review_id, reported_by)
 *     INDEX (status, created_at)            -- admin queue
 *     INDEX (reported_by)                   -- "lịch sử report của tôi"
 *   )
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('review_report')) {
            return;
        }

        Schema::create('review_report', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->integer('review_id');
            $t->integer('reported_by');
            $t->string('reason_code', 32);
            $t->text('description')->nullable();
            $t->unsignedTinyInteger('status')->default(0);
            $t->integer('resolved_by')->nullable();
            $t->timestamp('resolved_at')->nullable();
            $t->text('resolution_note')->nullable();
            $t->timestamps();

            $t->unique(['review_id', 'reported_by'], 'uniq_report_review_user');
            $t->index(['status', 'created_at'], 'idx_report_status_created');
            $t->index('reported_by', 'idx_report_user');

            $t->foreign('review_id', 'fk_report_review')
                ->references('id')->on('review')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_report');
    }
};
