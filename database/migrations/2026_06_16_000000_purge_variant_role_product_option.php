<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hướng B — product_option giờ CHỈ chứa custom field.
 *
 * Trục biến thể (variant axes), giá trị chọn được và metadata option đều suy
 * TRỰC TIẾP từ product_variant_attribute (single source of truth) —
 * xem ProductOptionService::buildVariantOptions. Vì vậy các row product_option
 * có option.role = variant đã trở thành dữ liệu thừa:
 *
 *   - Không nơi nào còn ĐỌC product_option role=variant (đã verify: chỉ
 *     buildVariantOptions từng đọc, nay đã rewrite sang nguồn variant attribute).
 *   - CartService::splitOptionPayload + CheckoutAddToCartRequest phân loại
 *     payload qua `option.role` (bảng option), KHÔNG đụng product_option.
 *
 * Migration này xoá các row đó để cưỡng chế bất biến "product_option = custom
 * field only". Cột option.role giữ nguyên (vẫn là nguồn phân loại option toàn
 * cục).
 *
 * Idempotent: chạy lại không xoá thêm. KHÔNG reversible — khai báo variant suy
 * lại được 100% từ product_variant_attribute, nên down() là no-op có chủ đích.
 *
 * Lưu ý vận hành (XAMPP opcache CLI, xem CLAUDE.md): nếu chạy migrate thấy còn
 * dùng code/SQL cũ, chạy `php -d opcache.enable_cli=0 artisan migrate`.
 */
return new class extends Migration
{
    /** option.role: 0 = custom_field, 1 = variant (snapshot, self-contained). */
    private const ROLE_VARIANT = 1;

    public function up(): void
    {
        if (! Schema::hasTable('product_option') || ! Schema::hasTable('option')) {
            return;
        }

        // DELETE ... JOIN: product_option có composite PK (product_id, option_id),
        // không có cột id — xoá theo điều kiện role của option liên kết.
        DB::statement(
            'DELETE po FROM product_option po
             JOIN `option` o ON o.id = po.option_id
             WHERE o.role = ?',
            [self::ROLE_VARIANT],
        );
    }

    public function down(): void
    {
        // No-op có chủ đích: khai báo variant là dữ liệu suy được từ
        // product_variant_attribute, không phục hồi từ migration.
    }
};
