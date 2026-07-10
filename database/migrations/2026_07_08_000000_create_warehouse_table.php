<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bảng `warehouse` — trước giờ product_stock / stock_movement /
 * stock_reservation chỉ mang cột warehouse_id "mồ côi" (không FK, không bảng
 * gốc, mặc định 1 theo core stock.default_warehouse_id).
 *
 * Thiết kế:
 *   - code UNIQUE: mã kho nghiệp vụ ('HN-01'), dùng trong CMS/import thay id.
 *   - is_active: kho ngừng vận hành thì tắt, KHÔNG xóa (movement lịch sử còn
 *     tham chiếu). SoftDeletes chỉ là lớp bảo hiểm thứ hai.
 *   - is_sellable: kho được tính vào tồn bán online. Kho hàng lỗi / kho trung
 *     chuyển đặt 0 → tầng query tồn kho lọc theo cột này.
 *   - priority: thứ tự ưu tiên khi chọn kho fulfil đơn (nhỏ = ưu tiên cao).
 *   - Địa chỉ theo cấu trúc VN sẵn có của order: zone/district/ward.
 *
 * FK dùng RESTRICT (mặc định), không cascade — không được xóa kho còn dữ liệu.
 * Idempotent: kiểm tra tồn tại trước mỗi bước.
 */
return new class () extends Migration {
    private function foreignKeyExists(string $table, string $constraint): bool
    {
        return DB::table('information_schema.TABLE_CONSTRAINTS')
            ->whereRaw('TABLE_SCHEMA = DATABASE()')
            ->where('TABLE_NAME', $table)
            ->where('CONSTRAINT_NAME', $constraint)
            ->where('CONSTRAINT_TYPE', 'FOREIGN KEY')
            ->exists();
    }

    public function up(): void
    {
        if (! Schema::hasTable('warehouse')) {
            Schema::create('warehouse', function (Blueprint $table) {
                $table->id();
                $table->string('code', 32)->unique();
                $table->string('name');
                $table->string('address')->nullable();
                $table->unsignedBigInteger('zone_id')->nullable();
                $table->unsignedBigInteger('district_id')->nullable();
                $table->unsignedBigInteger('ward_id')->nullable();
                $table->string('telephone', 32)->nullable();
                $table->integer('priority')->default(0);
                $table->boolean('is_active')->default(true);
                $table->boolean('is_sellable')->default(true);
                $table->timestamps();
                $table->softDeletes();

                $table->index(['is_active', 'is_sellable', 'priority'], 'idx_warehouse_pick');
            });
        }

        // Kho mặc định id=1 — khớp stock.default_warehouse_id trong core config
        // và default(1) trên các cột warehouse_id hiện có.
        DB::table('warehouse')->insertOrIgnore([
            'id'         => 1,
            'code'       => 'DEFAULT',
            'name'       => 'Kho mặc định',
            'priority'   => 0,
            'is_active'  => 1,
            'is_sellable' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // FK cho 3 bảng stock hiện có. Dọn orphan trước (warehouse_id không
        // tồn tại → gán về kho mặc định) để add constraint không fail.
        $tables = [
            'product_stock'     => 'fk_product_stock_warehouse',
            'stock_movement'    => 'fk_stock_movement_warehouse',
            'stock_reservation' => 'fk_stock_reservation_warehouse',
        ];

        foreach ($tables as $table => $constraint) {
            if (! Schema::hasTable($table) || $this->foreignKeyExists($table, $constraint)) {
                continue;
            }

            DB::statement(
                "UPDATE {$table} t
                    LEFT JOIN warehouse w ON w.id = t.warehouse_id
                    SET t.warehouse_id = 1
                    WHERE w.id IS NULL"
            );

            Schema::table($table, function (Blueprint $t) use ($constraint) {
                $t->foreign('warehouse_id', $constraint)
                    ->references('id')->on('warehouse')
                    ->restrictOnDelete();
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'product_stock'     => 'fk_product_stock_warehouse',
            'stock_movement'    => 'fk_stock_movement_warehouse',
            'stock_reservation' => 'fk_stock_reservation_warehouse',
        ] as $table => $constraint) {
            if (Schema::hasTable($table) && $this->foreignKeyExists($table, $constraint)) {
                DB::statement("ALTER TABLE {$table} DROP FOREIGN KEY {$constraint}");
            }
        }

        Schema::dropIfExists('warehouse');
    }
};
