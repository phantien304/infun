<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_special')) {
            return;
        }

        $defaultVariantIdByProduct = DB::table('product_variant')
            ->where('is_default', 1)
            ->whereNull('deleted_at')
            ->pluck('id', 'product_id');

        $now = now();
        $variantSpecialRows = [];

        foreach (DB::table('product_special')->get() as $productSpecial) {
            $defaultVariantId = $defaultVariantIdByProduct[$productSpecial->product_id] ?? null;
            if ($defaultVariantId === null) {
                continue;
            }

            $variantSpecialRows[] = [
                'product_variant_id' => $defaultVariantId,
                'product_id'         => $productSpecial->product_id,
                'user_group_id'      => $productSpecial->user_group_id,
                'priority'           => $productSpecial->priority,
                'price'              => $productSpecial->price,
                'date_start'         => $productSpecial->date_start,
                'date_end'           => $productSpecial->date_end,
                'created_at'         => $now,
                'updated_at'         => $now,
            ];
        }

        foreach (array_chunk($variantSpecialRows, 1000) as $chunk) {
            DB::table('product_variant_special')->insert($chunk);
        }

        Schema::drop('product_special');
    }

    public function down(): void
    {
        // Không tái tạo: product_special đã hợp nhất vào product_variant_special.
    }
};
