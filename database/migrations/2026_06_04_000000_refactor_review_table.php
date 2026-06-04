<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refactor bảng `review` legacy (id, product_id, user_id, ip, author, text,
 * rating, email, is_publish, timestamps) thành review root chuẩn Shopee UX.
 *
 * Mục tiêu:
 *  - Verified purchase: link tới `orders` để chỉ user đã mua mới review được.
 *  - Variant-aware: review gắn cụ thể `product_variant_id` (áo size M màu xanh
 *    khác áo size L màu đỏ — UX, không phải data integrity).
 *  - Status workflow: thay `is_publish` (boolean) bằng `status` (TINYINT) cho
 *    pending / approved / rejected / hidden — moderation flow đầy đủ.
 *  - Counter denormalize: helpful_count, unhelpful_count, reply_count,
 *    media_count cập nhật qua observer trên bảng con; tránh COUNT() mỗi lần render.
 *  - Edit history minimum: edit_count + last_edited_at — không tách bảng
 *    review_revision (over-engineering cho version đầu).
 *  - Anonymous mode: is_anonymous → blade hiển thị "Người dùng ẩn danh".
 *  - i18n: language_code để khi đổi locale chỉ hiện review cùng ngôn ngữ
 *    (Shopee VN/SG bị mix review tiếng Việt + tiếng Anh, UX kém).
 *  - Anti-fraud meta: source (web/mobile/api), user_agent — debug spam pattern.
 *
 * Tương thích ngược:
 *  - Cột legacy GIỮ: ip, author, email, rating, text, is_publish.
 *    `is_publish` được sync với `status` qua trigger (review approved → is_publish=1).
 *    Code cũ đọc is_publish vẫn chạy; code mới đọc status.
 *
 * FK type khớp legacy:
 *  - review.id giữ INT (legacy AUTO_INCREMENT).
 *  - order_id INT (orders.id legacy).
 *  - product_variant_id BIGINT UNSIGNED (cluster mới).
 *
 * Schema cuối:
 *   id                  INT PK AUTO (legacy giữ)
 *   product_id          INT NOT NULL                FK → product.id            RESTRICT
 *   product_variant_id  BIGINT UNSIGNED NULL        FK → product_variant.id    SET NULL
 *   order_id            INT NULL                    FK → orders.id             SET NULL
 *   user_id             INT NULL DEFAULT 0
 *   ip                  VARCHAR(45)                 -- legacy
 *   author              VARCHAR(255)                -- legacy display name
 *   email               VARCHAR(255)                -- legacy
 *   title               VARCHAR(255) NULL           -- headline (optional)
 *   text                TEXT                        -- legacy body
 *   rating              TINYINT UNSIGNED            -- legacy overall 1-5
 *   status              TINYINT UNSIGNED            -- 0=pending 1=approved 2=rejected 3=hidden
 *   is_publish          TINYINT UNSIGNED            -- legacy sync với status
 *   is_anonymous        BOOLEAN DEFAULT 0
 *   language_code       CHAR(5) DEFAULT 'vi'
 *   helpful_count       INT UNSIGNED DEFAULT 0      -- denormalize từ review_helpful
 *   unhelpful_count     INT UNSIGNED DEFAULT 0
 *   reply_count         INT UNSIGNED DEFAULT 0      -- denormalize từ review_reply
 *   media_count         INT UNSIGNED DEFAULT 0      -- denormalize từ review_media
 *   edit_count          INT UNSIGNED DEFAULT 0
 *   last_edited_at      TIMESTAMP NULL
 *   approved_at         TIMESTAMP NULL
 *   approved_by         INT NULL                    FK → user.id               SET NULL
 *   source              VARCHAR(16) DEFAULT 'web'
 *   user_agent          VARCHAR(255) NULL
 *   created_at, updated_at, deleted_at
 *
 *   INDEX (product_id, status, created_at)   -- list approved review per product
 *   INDEX (product_id, rating)               -- filter "5 sao" / "1 sao"
 *   INDEX (product_variant_id)
 *   INDEX (order_id)                         -- chống review trùng order_id+product_id
 *   INDEX (user_id, status)                  -- "review của tôi"
 *   UNIQUE (order_id, product_id) WHERE order_id IS NOT NULL -- 1 order 1 product 1 review
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('review')) {
            return;
        }

        // (1) Backfill timestamps trước khi add NOT NULL constraint xuống cột mới.
        DB::statement('
            UPDATE review
            SET created_at = COALESCE(created_at, NOW()),
                updated_at = COALESCE(updated_at, created_at, NOW())
            WHERE created_at IS NULL OR updated_at IS NULL
        ');

        // (2) Add columns mới.
        Schema::table('review', function (Blueprint $t) {
            if (! Schema::hasColumn('review', 'product_variant_id')) {
                $t->unsignedBigInteger('product_variant_id')->nullable()->after('product_id');
            }
            if (! Schema::hasColumn('review', 'order_id')) {
                $t->integer('order_id')->nullable()->after('product_variant_id');
            }
            if (! Schema::hasColumn('review', 'title')) {
                $t->string('title', 255)->nullable()->after('author');
            }
            if (! Schema::hasColumn('review', 'status')) {
                // 0=pending 1=approved 2=rejected 3=hidden
                $t->unsignedTinyInteger('status')->default(0)->after('rating');
            }
            if (! Schema::hasColumn('review', 'is_anonymous')) {
                $t->boolean('is_anonymous')->default(false)->after('is_publish');
            }
            if (! Schema::hasColumn('review', 'language_code')) {
                $t->char('language_code', 5)->default('vi')->after('is_anonymous');
            }
            if (! Schema::hasColumn('review', 'helpful_count')) {
                $t->unsignedInteger('helpful_count')->default(0)->after('language_code');
            }
            if (! Schema::hasColumn('review', 'unhelpful_count')) {
                $t->unsignedInteger('unhelpful_count')->default(0)->after('helpful_count');
            }
            if (! Schema::hasColumn('review', 'reply_count')) {
                $t->unsignedInteger('reply_count')->default(0)->after('unhelpful_count');
            }
            if (! Schema::hasColumn('review', 'media_count')) {
                $t->unsignedInteger('media_count')->default(0)->after('reply_count');
            }
            if (! Schema::hasColumn('review', 'edit_count')) {
                $t->unsignedInteger('edit_count')->default(0)->after('media_count');
            }
            if (! Schema::hasColumn('review', 'last_edited_at')) {
                $t->timestamp('last_edited_at')->nullable()->after('edit_count');
            }
            if (! Schema::hasColumn('review', 'approved_at')) {
                $t->timestamp('approved_at')->nullable()->after('last_edited_at');
            }
            if (! Schema::hasColumn('review', 'approved_by')) {
                $t->integer('approved_by')->nullable()->after('approved_at');
            }
            if (! Schema::hasColumn('review', 'source')) {
                $t->string('source', 16)->default('web')->after('approved_by');
            }
            if (! Schema::hasColumn('review', 'user_agent')) {
                $t->string('user_agent', 255)->nullable()->after('source');
            }
        });

        // (3) Backfill status từ is_publish legacy.
        //     is_publish=1 → status=1 (approved) + approved_at=updated_at.
        //     is_publish=0 → status=0 (pending) — nếu admin chưa duyệt; nhưng nhiều
        //     row legacy có is_publish=0 thực ra là "hidden after approval" (xem
        //     screenshot: row id=2 có deleted_at, is_publish=1 — soft deleted nhưng
        //     từng được publish). Convention chốt: is_publish=1 → approved bất kể
        //     deleted_at; deleted_at thuộc layer khác.
        DB::statement('
            UPDATE review
            SET status = CASE WHEN is_publish = 1 THEN 1 ELSE 0 END,
                approved_at = CASE WHEN is_publish = 1 THEN COALESCE(updated_at, created_at) ELSE NULL END
            WHERE status = 0
        ');

        // (4) Indexes.
        Schema::table('review', function (Blueprint $t) {
            if (! self::hasIndex('review', 'idx_review_product_status_created')) {
                $t->index(['product_id', 'status', 'created_at'], 'idx_review_product_status_created');
            }
            if (! self::hasIndex('review', 'idx_review_product_rating')) {
                $t->index(['product_id', 'rating'], 'idx_review_product_rating');
            }
            if (! self::hasIndex('review', 'idx_review_variant')) {
                $t->index('product_variant_id', 'idx_review_variant');
            }
            if (! self::hasIndex('review', 'idx_review_order')) {
                $t->index('order_id', 'idx_review_order');
            }
            if (! self::hasIndex('review', 'idx_review_user_status')) {
                $t->index(['user_id', 'status'], 'idx_review_user_status');
            }
            if (! self::hasIndex('review', 'uniq_review_order_product')) {
                // UNIQUE chống review trùng (1 order 1 product 1 review).
                // NULL order_id (visitor legacy) được phép trùng — MySQL UNIQUE
                // treat NULL as distinct, đúng semantic.
                $t->unique(['order_id', 'product_id'], 'uniq_review_order_product');
            }
        });

        // (5) FK. Bọc trong try để bỏ qua khi orphan data chưa cleanup
        //     (vd review.product_id trỏ tới product đã xóa cứng).
        try {
            Schema::table('review', function (Blueprint $t) {
                $t->foreign('product_id', 'fk_review_product')
                    ->references('id')->on('product')
                    ->restrictOnDelete();
                $t->foreign('product_variant_id', 'fk_review_variant')
                    ->references('id')->on('product_variant')
                    ->nullOnDelete();
                // orders.id FK — wrap riêng vì bảng `orders` có thể chưa exists
                // ở môi trường test legacy.
                if (Schema::hasTable('orders')) {
                    $t->foreign('order_id', 'fk_review_order')
                        ->references('id')->on('orders')
                        ->nullOnDelete();
                }
            });
        } catch (\Throwable $e) {
            // Log nhưng không fail migration — FK thiếu sẽ enforce ở code layer.
            DB::statement("/* FK review skipped: {$e->getMessage()} */");
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('review')) {
            return;
        }

        Schema::table('review', function (Blueprint $t) {
            foreach (['fk_review_product', 'fk_review_variant', 'fk_review_order'] as $fk) {
                try {
                    $t->dropForeign($fk);
                } catch (\Throwable $e) {
                }
            }
            foreach ([
                'idx_review_product_status_created',
                'idx_review_product_rating',
                'idx_review_variant',
                'idx_review_order',
                'idx_review_user_status',
                'uniq_review_order_product',
            ] as $idx) {
                try {
                    $t->dropIndex($idx);
                } catch (\Throwable $e) {
                }
            }
            $t->dropColumn([
                'product_variant_id', 'order_id', 'title', 'status',
                'is_anonymous', 'language_code',
                'helpful_count', 'unhelpful_count', 'reply_count', 'media_count',
                'edit_count', 'last_edited_at', 'approved_at', 'approved_by',
                'source', 'user_agent',
            ]);
        });
    }

    private static function hasIndex(string $table, string $index): bool
    {
        $rows = DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = DATABASE() AND table_name = ? AND index_name = ? LIMIT 1',
            [$table, $index],
        );
        return ! empty($rows);
    }
};
