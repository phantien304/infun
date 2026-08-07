<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `stock_status` lạc loài so với mọi bảng dropdown khác của form Product
 * (length_class/weight_class/category/user_group... đều đã tách _description
 * từ trước) — nó vẫn giữ nguyên shape gốc OpenCart: `language_code` nằm
 * thẳng trong PK kép (id, language_code), cột `name` nằm trên chính bảng.
 *
 * Bất tiện này từng gây sự cố thật: StockStatusRepository::listWithDescription()
 * (bản cũ) gọi with('description') như mọi repo khác → RelationNotFoundException
 * (model không có relation đó) → GET /resource 500 → lỗi bị catch() nuốt im
 * lặng → toàn bộ dropdown form Product (manufacturer, filter...) rỗng theo,
 * không chỉ riêng stock_status. Bản vá tạm dùng forLocale() trực tiếp trên
 * StockStatus thay vì with('description') — né được nhưng không dọn gốc,
 * và là bẫy chờ ai đó copy pattern with('description') từ chỗ khác vào đây.
 *
 * Migration này tách đúng theo khuôn length_class/length_class_description
 * (không FK constraint DB — cascade xoá xử lý ở tầng app qua
 * HasCascadeRelations/$destroyRelations, giống mọi *_description khác).
 *
 * IDEMPOTENT (2026-08-06, sau lần chạy đầu bị lỗi 1075 giữa chừng): MySQL
 * chạy DDL (Schema::create/table) tự COMMIT ngầm dù đang trong transaction
 * migration của Laravel — nghĩa là INSERT copy dữ liệu + DELETE dedupe ở
 * dưới đã LỌT qua thành công và ở lại DB thật dù toàn bộ migration bị coi là
 * "chưa chạy" (Laravel không ghi vào bảng `migrations` vì exception ném ra
 * sau đó). Mỗi bước dưới đây tự kiểm tra trạng thái trước khi làm, để
 * `php artisan migrate` chạy lại an toàn từ đúng chỗ dang dở, không lỗi
 * "table already exists" hay chèn trùng PK.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('stock_status_description')) {
            Schema::create('stock_status_description', function (Blueprint $table) {
                $table->integer('stock_status_id');
                $table->string('language_code', 11);
                $table->string('name', 32)->nullable();
                $table->timestamp('created_at')->nullable();
                $table->timestamp('updated_at')->nullable();
                $table->primary(['stock_status_id', 'language_code']);
            });
        }

        // Chỉ copy nếu bảng dịch còn trống — tránh lỗi trùng PK
        // (stock_status_id, language_code) nếu lần chạy trước đã lọt qua
        // bước này rồi mới chết ở ALTER TABLE bên dưới.
        if ((int) DB::table('stock_status_description')->count() === 0) {
            DB::statement('
                INSERT INTO stock_status_description (stock_status_id, language_code, name, created_at, updated_at)
                SELECT id, language_code, name, created_at, updated_at FROM stock_status
            ');
        }

        // Dedupe: giữ lại đúng 1 dòng/id (dòng có language_code nhỏ nhất theo
        // thứ tự chuỗi — tuỳ ý, vì name/language_code sắp bị xoá khỏi dòng
        // còn lại nên nội dung dòng "thắng" không còn ý nghĩa). An toàn chạy
        // lại nhiều lần — không còn dòng trùng id thì DELETE này là no-op.
        if (Schema::hasColumn('stock_status', 'language_code')) {
            DB::statement('
                DELETE s1 FROM stock_status s1
                INNER JOIN stock_status s2
                    ON s1.id = s2.id AND s1.language_code > s2.language_code
            ');

            // QUAN TRỌNG — lỗi 1075 thực tế đã gặp: Laravel Schema Blueprint
            // KHÔNG gộp nhiều lệnh trong 1 closure thành 1 câu ALTER TABLE
            // duy nhất như tưởng — mỗi $table->xxx() vẫn bắn 1 câu ALTER
            // TABLE riêng tuần tự. Tách dropPrimary() ra 1 câu ALTER riêng
            // rồi mới ADD PRIMARY KEY ở câu sau khiến MySQL thấy cột
            // AUTO_INCREMENT `id` không thuộc key nào ở thời điểm kết thúc
            // câu ALTER "DROP PRIMARY KEY" → 1075 "there can be only one
            // auto column and it must be defined as a key". Bắt buộc dùng 1
            // câu SQL thô duy nhất (DB::statement), KHÔNG dùng Blueprint cho
            // đoạn đổi khoá chính này.
            DB::statement('
                ALTER TABLE stock_status
                    DROP PRIMARY KEY,
                    DROP COLUMN language_code,
                    DROP COLUMN name,
                    ADD PRIMARY KEY (id)
            ');
        }
    }

    /**
     * Best-effort — đủ để huỷ migration này trong lúc dev (chưa có dữ liệu đa
     * ngôn ngữ mới ghi thêm qua bảng đã tách). KHÔNG khôi phục lại N dòng/id
     * (mỗi ngôn ngữ 1 dòng) như trước — mỗi id chỉ lấy lại đúng 1 dòng
     * (language_code nhỏ nhất). Cần rollback thật sự trên data đã tách lâu +
     * đã có thêm ngôn ngữ mới → khôi phục từ backup, đừng tin down() này.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('stock_status', 'language_code')) {
            // Cùng lý do 1075 ở up() — 1 câu SQL thô duy nhất, không tách
            // Blueprint.
            DB::statement("
                ALTER TABLE stock_status
                    DROP PRIMARY KEY,
                    ADD COLUMN language_code VARCHAR(11) NOT NULL DEFAULT '' AFTER id,
                    ADD COLUMN name VARCHAR(32) NULL AFTER language_code,
                    ADD PRIMARY KEY (id, language_code)
            ");

            DB::statement('
                UPDATE stock_status s
                INNER JOIN stock_status_description d ON d.stock_status_id = s.id
                SET s.language_code = d.language_code, s.name = d.name
                WHERE d.language_code = (
                    SELECT MIN(language_code) FROM stock_status_description WHERE stock_status_id = s.id
                )
            ');
        }

        Schema::dropIfExists('stock_status_description');
    }
};
