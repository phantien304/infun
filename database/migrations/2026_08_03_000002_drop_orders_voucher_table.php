<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drop bảng legacy `orders_voucher` (port từ OpenCart `oc_order_voucher`).
 *
 * Vai trò cũ: lưu dòng "khách MUA gift certificate trong đơn" — người gửi/nhận,
 * lời nhắn, theme, mệnh giá. Đối xứng với `voucher_history` = "voucher ĐƯỢC
 * DÙNG" (1 voucher : N row, có status applied/confirmed/refunded).
 *
 * Vì sao bỏ:
 *  - Bảng RỖNG trong dump hiện tại (infun_xampp.sql), chưa từng có row.
 *  - Mọi cột đều là tập con của bảng `voucher` (from_*\/to_*\/message\/
 *    voucher_theme_id/amount đã copy sang), trừ `description` không ai đọc.
 *  - Quan hệ "voucher này sinh từ đơn nào" đã do `voucher.order_id` gánh; luồng
 *    voucher tặng thì truy vết qua `voucher_reward_grant.order_id`.
 *  - Caller duy nhất là check `$linkOk` trong `VoucherRepository::resolveVoucher`
 *    — thừa vì đã có `voucher.order_id`, đã xoá cùng migration này.
 *  - Model `App\Models\Entities\OrdersVoucher` là orphan (trỏ nhầm vào tên bảng
 *    `order_voucher` không tồn tại) — đã xoá.
 *
 * `down()` tái tạo đúng DDL gốc (không backfill được vì bảng vốn rỗng).
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('orders_voucher');
    }

    public function down(): void
    {
        if (Schema::hasTable('orders_voucher')) {
            return;
        }

        DB::statement(
            'CREATE TABLE `orders_voucher` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `order_id` int(11) NOT NULL,
                `voucher_id` int(11) NOT NULL,
                `description` varchar(255) DEFAULT NULL,
                `code` varchar(10) DEFAULT NULL,
                `from_name` varchar(64) DEFAULT NULL,
                `from_email` varchar(96) DEFAULT NULL,
                `to_name` varchar(64) DEFAULT NULL,
                `to_email` varchar(96) DEFAULT NULL,
                `voucher_theme_id` int(11) DEFAULT NULL,
                `message` text DEFAULT NULL,
                `amount` decimal(15,4) DEFAULT NULL,
                `created_at` timestamp NULL DEFAULT NULL,
                `updated_at` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`) USING BTREE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci ROW_FORMAT=DYNAMIC'
        );
    }
};
