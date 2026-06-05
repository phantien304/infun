<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed dữ liệu mặc định cho cluster review:
 *
 *  (1) 5 tiêu chí Shopee chuẩn (review_criteria + review_criteria_description vi/en).
 *  (2) 8 tag phổ biến (review_tag + review_tag_description vi).
 *  (3) Backfill review_rating từ review legacy:
 *       Mỗi row review có rating > 0 → tạo 1 review_rating với criteria "overall"
 *       (= criteria 'quality' mặc định) — bảo toàn dữ liệu rating cũ trong schema mới.
 *
 * KHÔNG dùng Eloquent seeder — DB::table raw để chạy trước khi model layer sẵn sàng.
 *
 * Idempotent: dùng INSERT IGNORE / NOT EXISTS để chạy lại không lỗi.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('review_criteria')) {
            return;
        }

        $now = now();

        // (1) Criteria — 5 tiêu chí Shopee chuẩn.
        $criteria = [
            ['code' => 'quality',            'icon' => 'star',     'sort_order' => 1, 'is_required' => 1,
             'vi' => ['Chất lượng sản phẩm', 'Sản phẩm có đúng như mô tả không?'],
             'en' => ['Product Quality',     'Does the product match the description?']],
            ['code' => 'description_match',  'icon' => 'check',    'sort_order' => 2, 'is_required' => 0,
             'vi' => ['Đúng mô tả',          'Sản phẩm có giống ảnh và mô tả?'],
             'en' => ['Matches Description', 'Does the product match the photos and description?']],
            ['code' => 'service',            'icon' => 'message',  'sort_order' => 3, 'is_required' => 0,
             'vi' => ['Dịch vụ shop',        'Shop tư vấn và hỗ trợ có nhiệt tình?'],
             'en' => ['Seller Service',      'Was the seller helpful and responsive?']],
            ['code' => 'packaging',          'icon' => 'box',      'sort_order' => 4, 'is_required' => 0,
             'vi' => ['Đóng gói',            'Sản phẩm được đóng gói cẩn thận?'],
             'en' => ['Packaging',           'Was the product packaged properly?']],
            ['code' => 'shipping',           'icon' => 'truck',    'sort_order' => 5, 'is_required' => 0,
             'vi' => ['Vận chuyển',          'Thời gian giao hàng nhanh hay chậm?'],
             'en' => ['Shipping',            'How fast was the delivery?']],
        ];

        foreach ($criteria as $c) {
            $existing = DB::table('review_criteria')->where('code', $c['code'])->first();
            if ($existing) {
                $id = $existing->id;
            } else {
                $id = DB::table('review_criteria')->insertGetId([
                    'code'              => $c['code'],
                    'icon'              => $c['icon'],
                    'sort_order'        => $c['sort_order'],
                    'is_required'       => $c['is_required'],
                    'is_active'         => 1,
                    'created_at'        => $now,
                    'updated_at'        => $now,
                ]);
            }

            foreach (['vi', 'en'] as $lang) {
                DB::table('review_criteria_description')->updateOrInsert(
                    ['review_criteria_id' => $id, 'language_code' => $lang],
                    [
                        'name'       => $c[$lang][0],
                        'hint'       => $c[$lang][1],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }
        }

        // (2) Tags — 8 chip phổ biến.
        if (Schema::hasTable('review_tag')) {
            $tags = [
                ['code' => 'great_quality',     'vi' => 'Chất lượng tốt'],
                ['code' => 'as_described',      'vi' => 'Đúng như mô tả'],
                ['code' => 'fast_delivery',     'vi' => 'Giao hàng nhanh'],
                ['code' => 'nice_packaging',    'vi' => 'Đóng gói đẹp'],
                ['code' => 'good_value',        'vi' => 'Đáng tiền'],
                ['code' => 'enthusiastic_shop', 'vi' => 'Shop nhiệt tình'],
                ['code' => 'will_rebuy',        'vi' => 'Sẽ mua lại'],
                ['code' => 'recommend',         'vi' => 'Đề xuất bạn bè'],
            ];

            foreach ($tags as $tag) {
                $existing = DB::table('review_tag')->where('code', $tag['code'])->first();
                if ($existing) {
                    $tagId = $existing->id;
                } else {
                    $tagId = DB::table('review_tag')->insertGetId([
                        'code'              => $tag['code'],
                        'usage_count'       => 0,
                        'is_auto_generated' => 0,
                        'is_active'         => 1,
                        'created_at'        => $now,
                        'updated_at'        => $now,
                    ]);
                }

                DB::table('review_tag_description')->updateOrInsert(
                    ['review_tag_id' => $tagId, 'language_code' => 'vi'],
                    [
                        'name'       => $tag['vi'],
                        'created_at' => $now,
                        'updated_at' => $now,
                    ],
                );
            }
        }

        // (3) Backfill review_rating từ review legacy.
        //     Map: rating overall của review cũ → review_rating với criteria 'quality'.
        //     Không insert nếu pair (review_id, criteria_id) đã có (idempotent).
        $qualityId = DB::table('review_criteria')->where('code', 'quality')->value('id');
        if ($qualityId && Schema::hasTable('review_rating')) {
            DB::statement('
                INSERT IGNORE INTO review_rating (review_id, review_criteria_id, rating, created_at, updated_at)
                SELECT r.id, ?, r.rating, COALESCE(r.created_at, NOW()), COALESCE(r.updated_at, NOW())
                FROM review r
                WHERE r.rating BETWEEN 1 AND 5
                  AND r.deleted_at IS NULL
            ', [$qualityId]);
        }
    }

    public function down(): void
    {
        // KHÔNG xóa criteria/tag mặc định — chúng có thể đã được dùng bởi
        // review_rating live; xóa criteria sẽ vi phạm FK RESTRICT.
        // Để rollback sạch: chạy migration `2026_06_04_000002` down (drop
        // review_rating trước), rồi rollback file này thủ công nếu cần.
        if (Schema::hasTable('review_rating')) {
            DB::table('review_rating')->truncate();
        }
    }
};
