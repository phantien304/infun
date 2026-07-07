<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Base khối lượng của hệ thống là Gram (config_weight_class_id = 2, value = 1).
 * Pound bị nhập sai 0.00045360 (1/2204.6 — nhầm chiều quy đổi);
 * đúng phải là 0.00220462 lb trên 1 gram.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('weight_class')
            ->where('id', 5)
            ->where('value', 0.00045360)
            ->update(['value' => 0.00220462, 'updated_at' => now()]);
    }

    public function down(): void
    {
        DB::table('weight_class')
            ->where('id', 5)
            ->where('value', 0.00220462)
            ->update(['value' => 0.00045360, 'updated_at' => now()]);
    }
};
