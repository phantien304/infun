<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Thêm FK option_value.option_id → option.id.
 *
 * Bảng option_value hiện không khai báo FK ở DB (schema gốc tạo bằng SQL thô
 * không declare). Hệ quả:
 *  - Có thể tồn tại option_value mồ côi (option_id trỏ tới option đã hard
 *    delete) — pivot product_variant_attribute sẽ orphan nếu kéo theo.
 *  - DB không bảo vệ khi xoá option đang được tham chiếu.
 *
 * Migration này:
 *  1. Audit orphan (option_id không tồn tại trong option hoặc option đã soft
 *     deleted) — log số lượng, KHÔNG tự xoá để admin quyết định.
 *  2. Nếu còn orphan → throw exception, bailout. Admin phải clean trước.
 *  3. Thêm FK với ON DELETE RESTRICT: chặn xoá option đang có option_value.
 *     Muốn xoá phải soft delete (set deleted_at) hoặc xoá option_value trước.
 *
 * Lưu ý: option có soft delete (deleted_at), option_value cũng đã có (user
 * confirm). FK chỉ check tồn tại row option theo id, KHÔNG quan tâm
 * deleted_at — chuẩn behavior. App layer dùng scope withTrashed()/withoutTrashed()
 * tuỳ context.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Audit orphan trước khi add FK
        $orphans = DB::table('option_value as ov')
            ->leftJoin('option as o', 'o.id', '=', 'ov.option_id')
            ->whereNull('o.id')
            ->count();

        if ($orphans > 0) {
            throw new \RuntimeException(sprintf(
                'Có %d row option_value trỏ tới option không tồn tại. Clean dữ liệu trước khi add FK. Chạy: SELECT ov.id, ov.option_id FROM option_value ov LEFT JOIN `option` o ON o.id = ov.option_id WHERE o.id IS NULL;',
                $orphans
            ));
        }

        Schema::table('option_value', function (Blueprint $table) {
            $table->foreign('option_id', 'fk_option_value_option')
                ->references('id')->on('option')
                ->restrictOnDelete()
                ->cascadeOnUpdate();
        });

        // Bonus: thêm index hỗ trợ lookup theo option_id (FK MySQL tự tạo
        // index nếu chưa có, nhưng khai báo tường minh để đọc schema rõ ràng).
        // Schema::table('option_value', function (Blueprint $table) {
        //     $table->index('option_id', 'idx_option_value_option');
        // });
        // ↑ comment out vì MySQL InnoDB tự tạo index dưới tên FK; thêm sẽ
        // duplicate. Bật khi DB engine khác.
    }

    public function down(): void
    {
        Schema::table('option_value', function (Blueprint $table) {
            $table->dropForeign('fk_option_value_option');
        });
    }
};
