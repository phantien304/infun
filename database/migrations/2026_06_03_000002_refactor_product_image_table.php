<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refactor `product_image` từ minimal table (id, product_id, image, sort_order)
 * thành gallery cluster đa năng.
 *
 * Nhu cầu mở rộng (CLAUDE.md mục "Gap design A: Gallery per variant chưa hỗ trợ"):
 *  - Ảnh per variant (áo xanh có gallery 5 ảnh khác áo đỏ).
 *  - Alt text cho SEO + accessibility.
 *  - Phân loại ảnh (main/gallery/thumbnail/zoom/360 — list page vs detail vs zoom modal).
 *  - Metadata (width, height, file_size, mime) cho lazy-loading, responsive srcset.
 *  - is_active để tạm ẩn ảnh không cần xóa.
 *  - Soft delete + FK enforce.
 *
 * Tương thích ngược:
 *  - product.image (cột legacy) GIỮ — ảnh đại diện đơn, truy cập nhanh không join.
 *  - product_variant.image (single string) GIỮ — ảnh đại diện per variant.
 *  - product_image trở thành EXTENSION: gallery nhiều ảnh, có thể gắn product
 *    hoặc variant cụ thể.
 *
 * Migration data cũ:
 *  - product_id = 0 → orphan legacy (catalog/demo ảnh chung), XÓA.
 *  - product_id > 0 nhưng không có product tương ứng → XÓA (chống FK fail).
 *  - Tất cả row còn lại → set type='gallery', is_active=1, alt=NULL (sẽ fallback
 *    sang product.name ở blade).
 *  - created_at/updated_at NULL → backfill bằng now() để cast cột timestamp NOT NULL.
 *
 * Schema cuối:
 *   id                  BIGINT PK
 *   product_id          INT NOT NULL    FK → product.id           CASCADE
 *   product_variant_id  BIGINT NULL     FK → product_variant.id   CASCADE
 *     -- NULL: ảnh của cả product (mọi variant share)
 *     -- Filled: ảnh riêng variant (áo xanh size M)
 *   image               VARCHAR(255) NOT NULL
 *   alt                 VARCHAR(255) NULL     -- blade fallback product.name khi NULL
 *   title               VARCHAR(255) NULL     -- tooltip / lightbox caption
 *   type                VARCHAR(16) NOT NULL  -- main / gallery / thumbnail / zoom / 360
 *   width               INT UNSIGNED NULL
 *   height              INT UNSIGNED NULL
 *   file_size           INT UNSIGNED NULL     -- bytes
 *   mime                VARCHAR(64) NULL
 *   sort_order          INT UNSIGNED DEFAULT 0
 *   is_active           BOOLEAN DEFAULT 1
 *   created_at, updated_at, deleted_at
 *
 *   INDEX (product_id, type, sort_order, is_active) — list ảnh per product theo loại
 *   INDEX (product_variant_id, sort_order) — list ảnh per variant
 *
 * KHÔNG tạo product_image_description i18n riêng — alt single column tiếng Việt
 * đủ cho dự án hiện tại. Nếu sau cần multi-locale alt, tạo migration bổ sung
 * tách `alt` ra description table (convention dự án).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_image')) {
            return;
        }

        DB::statement('SET foreign_key_checks=0');
        try {
            // (1) Cleanup orphan rows trước khi add FK.
            //     product_id = 0: legacy catalog/demo, không thuộc product cụ thể.
            $orphanZero = DB::table('product_image')->where('product_id', 0)->delete();
            // product_id > 0 nhưng product không tồn tại (đã xóa hoặc never existed).
            $orphanMissing = DB::statement('
                DELETE pi FROM product_image pi
                LEFT JOIN product p ON p.id = pi.product_id
                WHERE p.id IS NULL
            ');

            // (2) Backfill timestamps NULL → now.
            DB::statement('
                UPDATE product_image
                SET created_at = COALESCE(created_at, NOW()),
                    updated_at = COALESCE(updated_at, created_at, NOW())
                WHERE created_at IS NULL OR updated_at IS NULL
            ');

            // (3) Add columns mới.
            Schema::table('product_image', function (Blueprint $t) {
                if (! Schema::hasColumn('product_image', 'product_variant_id')) {
                    $t->unsignedBigInteger('product_variant_id')->nullable()->after('product_id');
                }
                if (! Schema::hasColumn('product_image', 'alt')) {
                    $t->string('alt', 255)->nullable()->after('image');
                }
                if (! Schema::hasColumn('product_image', 'title')) {
                    $t->string('title', 255)->nullable()->after('alt');
                }
                if (! Schema::hasColumn('product_image', 'type')) {
                    // VARCHAR thay ENUM — flexible, không phải ALTER TABLE khi
                    // thêm loại mới (vd 'video_thumbnail', 'ar_preview').
                    $t->string('type', 16)->default('gallery')->after('title');
                }
                if (! Schema::hasColumn('product_image', 'width')) {
                    $t->unsignedInteger('width')->nullable()->after('type');
                }
                if (! Schema::hasColumn('product_image', 'height')) {
                    $t->unsignedInteger('height')->nullable()->after('width');
                }
                if (! Schema::hasColumn('product_image', 'file_size')) {
                    $t->unsignedInteger('file_size')->nullable()->after('height')
                        ->comment('Bytes');
                }
                if (! Schema::hasColumn('product_image', 'mime')) {
                    $t->string('mime', 64)->nullable()->after('file_size');
                }
                if (! Schema::hasColumn('product_image', 'is_active')) {
                    $t->boolean('is_active')->default(true)->after('sort_order');
                }
                if (! Schema::hasColumn('product_image', 'deleted_at')) {
                    $t->softDeletes();
                }
            });

            // (4) Backfill type='gallery' cho row cũ (đã default sẵn ở step 3,
            //     nhưng đảm bảo idempotent nếu cột tồn tại sẵn không có default).
            DB::statement("UPDATE product_image SET type = 'gallery' WHERE type IS NULL OR type = ''");

            // (5) NOT NULL cho image + type sau backfill.
            DB::statement('ALTER TABLE product_image MODIFY image VARCHAR(255) NOT NULL');
            DB::statement("ALTER TABLE product_image MODIFY type VARCHAR(16) NOT NULL DEFAULT 'gallery'");
            DB::statement('ALTER TABLE product_image MODIFY product_id INT NOT NULL');
        } finally {
            DB::statement('SET foreign_key_checks=1');
        }

        // (6) FK + index.
        Schema::table('product_image', function (Blueprint $t) {
            $t->foreign('product_id', 'fk_product_image_product')
                ->references('id')->on('product')
                ->cascadeOnDelete();

            $t->foreign('product_variant_id', 'fk_product_image_variant')
                ->references('id')->on('product_variant')
                ->cascadeOnDelete();

            // List ảnh per product filtered theo type + active, ordered by sort_order.
            // Cover toàn bộ query trang detail "gallery sản phẩm".
            $t->index(
                ['product_id', 'type', 'is_active', 'sort_order'],
                'idx_product_image_product_type'
            );

            // List ảnh per variant — khi user click màu xanh, query gallery
            // theo product_variant_id.
            $t->index(
                ['product_variant_id', 'sort_order'],
                'idx_product_image_variant'
            );
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_image')) {
            return;
        }

        Schema::table('product_image', function (Blueprint $t) {
            $t->dropForeign('fk_product_image_product');
            $t->dropForeign('fk_product_image_variant');
            $t->dropIndex('idx_product_image_product_type');
            $t->dropIndex('idx_product_image_variant');

            $t->dropSoftDeletes();
            $t->dropColumn([
                'product_variant_id', 'alt', 'title', 'type',
                'width', 'height', 'file_size', 'mime', 'is_active',
            ]);
        });
    }
};
