<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * orders.idempotency_key — chốt cứng chống trùng đơn ở tầng DB.
 *
 * Ghi key (md5 của form-token) trong cùng transaction tạo đơn; UNIQUE đảm bảo
 * chỉ 1 đơn/khoá kể cả khi chạy nhiều instance sau load balancer hoặc khi cache
 * bị evict. Cột nullable + UNIQUE → nhiều NULL vẫn hợp lệ (đơn legacy / tạo từ
 * kênh khác không có key sẽ không đụng nhau).
 *
 * Sau khi thêm cột phải xoá schema-cache của bảng orders (HasSchemaCache tự suy
 * fillable từ `describe` đã cache) để cột mới được ghi qua fill().
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('orders', 'idempotency_key')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->string('idempotency_key', 64)->nullable();
                $table->unique('idempotency_key', 'uq_orders_idempotency_key');
            });
        }

        $this->forgetSchemaCache('orders');
    }

    public function down(): void
    {
        if (Schema::hasColumn('orders', 'idempotency_key')) {
            Schema::table('orders', function (Blueprint $table) {
                $table->dropUnique('uq_orders_idempotency_key');
                $table->dropColumn('idempotency_key');
            });
        }

        $this->forgetSchemaCache('orders');
    }

    private function forgetSchemaCache(string $table): void
    {
        $conn = DB::connection();
        cache()->forget('schema:'.$conn->getDatabaseName().':'.$conn->getDriverName().':'.$table);
    }
};
