<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đo qua slow query log lúc k6 load test (docs/SCALE-30K.md): query COUNT(*)
 * phân trang của trang list sản phẩm (`WHERE (date_available <= ? OR
 * date_available IS NULL) AND deleted_at IS NULL`, scope `dateAvailable`)
 * full table scan 491,840 dòng MỖI LẦN GỌI — 367 lần trong 1 cửa sổ k6 90s
 * chiếm tổng 31s CPU DB, drop hẳn `type: ALL`. Index cũ `idx_product_list`
 * (deleted_at, created_at) không cover được `date_available` nên vô dụng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product', function (Blueprint $table) {
            $table->index(['date_available', 'deleted_at'], 'idx_product_date_available');
        });
    }

    public function down(): void
    {
        Schema::table('product', function (Blueprint $table) {
            $table->dropIndex('idx_product_date_available');
        });
    }
};
