<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * DEPRECATED — bảng product_option_definition đã bị bỏ.
 *
 * Lý do gộp: legacy bảng `product_option` (product_id, option_id, value,
 * required) đã đủ vai trò "declare 1 product có option X". Tạo thêm
 * product_option_definition cho variant role là duplication — 2 bảng cùng
 * khai báo "product có option Y" chỉ khác cách lấy value (variant từ pivot
 * vs custom field từ cột value). Đáng lý ra dispatch theo `option.role`
 * trên 1 bảng duy nhất.
 *
 * Migration này giờ chỉ DROP bảng nếu đã tạo (cho DB đã chạy version cũ).
 * Lần migrate fresh sẽ là no-op.
 *
 * Code application phải đọc cả 2 role từ legacy `product_option`:
 *   $product->productOptions  → ProductOption model (legacy)
 *     - option.role = ROLE_VARIANT      → value column NULL, đọc values
 *                                          qua product_variant_attribute
 *     - option.role = ROLE_CUSTOM_FIELD → value column là default,
 *                                          user override lúc checkout
 *
 * Service ProductOptionService phải đổi từ productOptionDefinitions sang
 * productOptions, dispatch theo option.role.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('product_option_definition');
    }

    public function down(): void
    {
        // KHÔNG recreate — bảng này đã bỏ vĩnh viễn.
    }
};
