<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * (1) Soft delete cho `product_option` + `product_option_value` — đồng bộ với
 *     `option` / `option_value` (đã soft delete). Cascade soft-delete lo bởi
 *     HasCascadeRelations + $destroyRelations (không cần đổi FK).
 *
 *     Bất biến giữ UNIQUE: mỗi (product_id, option_id) / (product_option_id,
 *     option_value_id) chỉ MỘT dòng vật lý. Soft-delete chỉ bật deleted_at;
 *     re-declare thì RESTORE dòng cũ (ProductVariantWriter dùng withTrashed +
 *     un-trash) chứ không tạo dòng mới → không đụng UNIQUE.
 *
 * (2) `price` cho custom field — phụ phí khi chọn/nhập option (khắc tên, gói
 *     quà...). Dùng DECIMAL có DẤU (âm = giảm giá); KHÔNG theo kiểu 2 cột
 *     price + price_prefix của OpenCart (price_prefix đã không còn dùng).
 *       - product_option.price        : phụ phí cố định của field (vd text).
 *       - product_option_value.price  : phụ phí của từng lựa chọn picker.
 *     Default 0 → không đổi giá đơn hiện tại. (Cộng vào cart total là bước
 *     riêng ở CartService/CheckoutTotalService — xem CLAUDE.md việc còn nợ.)
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('product_option')) {
            Schema::table('product_option', function (Blueprint $t) {
                if (! Schema::hasColumn('product_option', 'price')) {
                    $t->decimal('price', 15, 4)->default(0)->after('value')
                        ->comment('Phụ phí custom field; âm = giảm');
                }
                if (! Schema::hasColumn('product_option', 'deleted_at')) {
                    $t->softDeletes();
                }
            });
        }

        if (Schema::hasTable('product_option_value')) {
            Schema::table('product_option_value', function (Blueprint $t) {
                if (! Schema::hasColumn('product_option_value', 'price')) {
                    $t->decimal('price', 15, 4)->default(0)->after('image')
                        ->comment('Phụ phí lựa chọn picker; âm = giảm');
                }
                if (! Schema::hasColumn('product_option_value', 'deleted_at')) {
                    $t->softDeletes();
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('product_option')) {
            Schema::table('product_option', function (Blueprint $t) {
                if (Schema::hasColumn('product_option', 'deleted_at')) {
                    $t->dropSoftDeletes();
                }
                if (Schema::hasColumn('product_option', 'price')) {
                    $t->dropColumn('price');
                }
            });
        }

        if (Schema::hasTable('product_option_value')) {
            Schema::table('product_option_value', function (Blueprint $t) {
                if (Schema::hasColumn('product_option_value', 'deleted_at')) {
                    $t->dropSoftDeletes();
                }
                if (Schema::hasColumn('product_option_value', 'price')) {
                    $t->dropColumn('price');
                }
            });
        }
    }
};
