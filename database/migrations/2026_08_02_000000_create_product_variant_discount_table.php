<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * product_variant_discount — chiết khấu theo SỐ LƯỢNG mua (quantity-tier),
 * mirror `product_variant_special` (Hướng B, xem
 * 2026_06_06_000000_create_product_variant_special_table + docs/CLAUDE.md
 * "Cluster variant special") nhưng thêm cột `quantity` (ngưỡng số lượng).
 *
 * Lý do tạo bảng mới thay vì sửa `product_discount`:
 *   - `product_discount` là bảng gốc OpenCart, khoá theo `product_id` với 1
 *     `price` tuyệt đối cho CẢ sản phẩm. Từ khi giá chuyển xuống
 *     `product_variant.price` (mỗi variant giá riêng), 1 dòng discount không
 *     còn đại diện đúng cho sản phẩm có nhiều variant giá khác nhau.
 *   - Audit thực tế (2026-08-02): `product_discount` KHÔNG được đọc ở bất kỳ
 *     đâu trong CartService/CheckoutTotalService — dữ liệu admin nhập vào tab
 *     Discount hoàn toàn không ảnh hưởng giá checkout. Trong khi đó cơ chế
 *     ĐÚNG hướng kiến trúc mới (product_variant_special) đã có sẵn và đang
 *     chạy thật. Bảng này đưa chiết khấu theo số lượng vào cùng mô hình đó.
 *
 * Áp dụng lúc tính giá (CartService::resolvePrice): trong các variant_discount
 * đang active (date range + user_group) mà `quantity <= số lượng mua`, chọn
 * dòng có `quantity` lớn nhất (ngưỡng sâu nhất đã đạt), tie-break `priority`
 * cao nhất thắng. Giá cuối = MIN(variant.price, special đang active, tier
 * discount phù hợp) — không cộng dồn nhiều cơ chế, khách luôn được giá thấp
 * nhất trong các chương trình đang chạy.
 *
 * Migrate dữ liệu cũ: mỗi dòng `product_discount` (theo product_id) backfill
 * sang default variant của product đó (giống hệt cách
 * 2026_06_18_000000_merge_product_special_into_variant_special đã làm cho
 * product_special → product_variant_special). Sau backfill, DROP
 * product_discount — bảng đã dead-code trên toàn bộ app trước khi migration
 * này chạy nên an toàn để xoá thẳng, không cần giữ lại "for reference".
 */
return new class () extends Migration {
    public function up(): void
    {
        // Idempotent (2026-08-02, lần 2): lần chạy đầu OOM giữa chừng (xem
        // ghi chú backfill bên dưới) — CREATE TABLE là DDL, MySQL implicit-
        // commit ngay dù transaction migration sau đó "fail", nên bảng có
        // thể đã tồn tại từ lần chạy trước. Bọc hasTable() để chạy lại an toàn.
        if (! Schema::hasTable('product_variant_discount')) {
            Schema::create('product_variant_discount', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('product_variant_id');
                // FK tới product.id legacy là INT SIGNED (xem CLAUDE.md "Convention
                // DB / migration" — FK column khớp chính xác type của product_variant_special).
                $table->integer('product_id')
                    ->comment('Denormalize từ product_variant.product_id; backfill aggregate GROUP BY product');
                $table->unsignedInteger('user_group_id')->default(1);
                $table->unsignedInteger('quantity')->default(1)
                    ->comment('Ngưỡng số lượng mua tối thiểu để áp giá này (tier)');
                $table->integer('priority')->default(0)
                    ->comment('Cao nhất thắng khi nhiều tier cùng quantity overlap');
                $table->decimal('price', 15, 2)
                    ->comment('Giá tuyệt đối khi đạt ngưỡng quantity — KHÔNG phải delta/%');
                $table->dateTime('date_start')->nullable();
                $table->dateTime('date_end')->nullable();
                $table->timestamps();
                $table->softDeletes();

                $table->foreign('product_variant_id')
                    ->references('id')->on('product_variant')
                    ->cascadeOnDelete();
                $table->foreign('product_id')
                    ->references('id')->on('product')
                    ->cascadeOnDelete();

                // Covering index cho lookup runtime: WHERE product_variant_id=?
                // AND user_group_id=? AND quantity<=? AND date range, ORDER BY
                // quantity DESC, priority DESC.
                $table->index(
                    ['product_variant_id', 'user_group_id', 'quantity', 'priority', 'date_start', 'date_end'],
                    'idx_pvd_lookup'
                );
                $table->index(['product_id', 'user_group_id'], 'idx_pvd_product');
            });
        }

        // BUG đã fix (2026-08-02, lần 2): bản đầu dùng
        // DB::table('product_discount')->get() nạp TOÀN BỘ bảng vào PHP
        // memory 1 lần rồi loop từng dòng ⇒ "Allowed memory size of
        // 134217728 bytes exhausted" trên môi trường thật (product_discount
        // là bảng OpenCart legacy, tồn đọng dữ liệu nhiều năm, số dòng lớn
        // hơn nhiều so với giả định lúc viết migration). Đổi sang
        // INSERT...SELECT chạy thẳng trong MySQL — không nạp bất kỳ dòng nào
        // vào PHP, không phụ thuộc memory_limit dù bảng lớn cỡ nào. Guard
        // doesntExist() để an toàn khi chạy lại (lần trước OOM xảy ra ngay ở
        // bước đọc, TRƯỚC khi insert dòng nào, nhưng vẫn phòng hờ double-insert
        // nếu retry sau khi đã insert 1 phần).
        if (Schema::hasTable('product_discount') && DB::table('product_variant_discount')->doesntExist()) {
            DB::statement('
                INSERT INTO product_variant_discount
                    (product_variant_id, product_id, user_group_id, quantity, priority, price, date_start, date_end, created_at, updated_at)
                SELECT pv.id, pd.product_id, pd.user_group_id, pd.quantity, pd.priority, pd.price, pd.date_start, pd.date_end, NOW(), NOW()
                FROM product_discount pd
                INNER JOIN product_variant pv
                    ON pv.product_id = pd.product_id
                    AND pv.is_default = 1
                    AND pv.deleted_at IS NULL
            ');
        }

        if (Schema::hasTable('product_discount')) {
            Schema::drop('product_discount');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_discount');
    }
};
