<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Seed ảnh gallery GẮN VARIANT để test listener variant-gallery ở trang chi tiết.
 *
 * Gán các đường dẫn ảnh gallery chung sẵn có của product cho từng variant
 * (set product_variant_id) → ProductOptionService::buildVariantGallery populate
 * window.variantGallery → JS swap toàn slider khi user chốt variant.
 *
 * Dùng:
 *   php artisan variant-gallery:seed --product=492466 --per=3
 *   php artisan variant-gallery:seed --per=2 --truncate      # mọi product có variant
 */
class SeedVariantGalleryCommand extends Command
{
    protected $signature = 'variant-gallery:seed
        {--product= : ID product cụ thể; bỏ trống = mọi product có variant}
        {--per=3 : Số ảnh gán cho mỗi variant}
        {--truncate : Xoá ảnh đã gắn variant (product_variant_id) trước khi seed}';

    protected $description = 'Gán ảnh gallery cho từng product_variant (test variant-gallery swap)';

    public function handle(): int
    {
        $per = max(1, (int) $this->option('per'));

        $productIds = $this->option('product')
            ? [(int) $this->option('product')]
            : DB::table('product_variant')->whereNull('deleted_at')
                ->distinct()->pluck('product_id')->all();

        if (empty($productIds)) {
            $this->warn('Không có product nào có variant. Chạy `php artisan variants:seed` trước.');

            return self::FAILURE;
        }

        $now = Carbon::now();
        $totalRows = 0;
        $bar = $this->output->createProgressBar(count($productIds));
        $bar->start();

        foreach ($productIds as $productId) {
            $variants = DB::table('product_variant')
                ->where('product_id', $productId)->whereNull('deleted_at')
                ->orderBy('sort_order')->orderBy('id')
                ->get(['id', 'image']);
            if ($variants->isEmpty()) {
                $bar->advance();
                continue;
            }

            if ($this->option('truncate')) {
                DB::table('product_image')
                    ->whereIn('product_variant_id', $variants->pluck('id')->all())
                    ->delete();
            }

            // Ảnh gallery chung của product (product_variant_id NULL) làm nguồn —
            // mỗi variant lấy 1 subset khác nhau để swap thấy rõ. Không có thì
            // fallback ảnh của chính variant.
            $shared = DB::table('product_image')
                ->where('product_id', $productId)
                ->whereNull('product_variant_id')
                ->where('is_active', 1)
                ->orderBy('sort_order')->orderBy('id')
                ->pluck('image')->all();

            $rows = [];
            foreach ($variants->values() as $index => $variant) {
                for ($k = 0; $k < $per; $k++) {
                    $image = ! empty($shared)
                        ? $shared[($index * $per + $k) % count($shared)]
                        : $variant->image;
                    if (! $image) {
                        continue;
                    }
                    $rows[] = [
                        'product_id'         => $productId,
                        'product_variant_id' => $variant->id,
                        'image'              => $image,
                        'alt'                => "variant {$variant->id} #".($k + 1),
                        'type'               => 'gallery',
                        'sort_order'         => $k,
                        'is_active'          => 1,
                        'created_at'         => $now,
                        'updated_at'         => $now,
                    ];
                }
            }

            if ($rows) {
                DB::table('product_image')->insert($rows);
                $totalRows += count($rows);
            }
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Xong: {$totalRows} product_image gắn variant trên ".count($productIds).' product.');
        $this->line('Nhớ chạy: php artisan cache:clear (trang chi tiết cache productImages).');

        return self::SUCCESS;
    }
}
