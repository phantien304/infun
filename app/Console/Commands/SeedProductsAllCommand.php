<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Wrapper orchestrator: seed N product với MIX simple + variant trong 1 lệnh.
 *
 * Tận dụng lại 4 command đã có thay vì viết lại:
 *   1. products:seed         — N product (không variant)
 *   2. variants:seed         — gắn variant Color×Size cho --variant-percent% product
 *   3. simple-variants:seed  — gắn 1 default variant cho phần product còn lại
 *   4. specials:seed         — (optional) gắn campaign giảm giá lên variant
 *
 * Mặc định 500_000 product, 40% variant (200k product variant + 300k simple).
 *
 * Cách dùng:
 *   php artisan products:seed-all
 *   php artisan products:seed-all --total=500000 --variant-percent=40
 *   php artisan products:seed-all --total=100000 --variant-percent=60 --no-specials
 *   php artisan products:seed-all --truncate          # reset trước khi seed
 *
 * Sau khi xong:
 *   php artisan scout:import "App\Models\Entities\Product"   # đẩy lên Meilisearch
 */
class SeedProductsAllCommand extends Command
{
    protected $signature = 'products:seed-all
        {--total=500000           : Tổng số product}
        {--variant-percent=40     : % product nhận variant Color×Size (còn lại là simple)}
        {--variant-min=2          : Số variant tối thiểu / product variant}
        {--variant-max=4          : Số variant tối đa / product variant}
        {--special-percent=30     : % variant nhận campaign giảm giá}
        {--chunk=2000             : Batch size khi insert}
        {--no-specials            : Bỏ qua bước seed specials}
        {--no-scout               : Bỏ qua gợi ý scout:import cuối}
        {--truncate               : Truncate toàn bộ cluster product trước khi seed}';

    protected $description = 'Seed N product mix SIMPLE + VARIANT trong 1 lệnh (orchestrate products:seed + variants:seed + simple-variants:seed + specials:seed)';

    public function handle(): int
    {
        $total          = (int) $this->option('total');
        $variantPercent = max(0, min(100, (int) $this->option('variant-percent')));
        $variantMin     = max(1, (int) $this->option('variant-min'));
        $variantMax     = max($variantMin, (int) $this->option('variant-max'));
        $specialPercent = max(0, min(100, (int) $this->option('special-percent')));
        $chunk          = max(500, (int) $this->option('chunk'));
        $noSpecials     = (bool) $this->option('no-specials');
        $noScout        = (bool) $this->option('no-scout');
        $truncate       = (bool) $this->option('truncate');

        // Heads-up summary trước khi chạy — giúp user catch typo trước
        // khi đốt 5+ phút seed.
        $estVariantProducts = (int) round($total * $variantPercent / 100);
        $estSimpleProducts  = $total - $estVariantProducts;
        $estVariantRows     = $estVariantProducts * (int) round(($variantMin + $variantMax) / 2);
        $estTotalVariants   = $estVariantRows + $estSimpleProducts; // simple = 1 default

        $this->info("Plan:");
        $this->line("  Tổng product:     {$total}");
        $this->line("    - variant:      ~{$estVariantProducts} ({$variantPercent}%)");
        $this->line("    - simple:       ~{$estSimpleProducts}");
        $this->line("  Tổng variant:     ~{$estTotalVariants}");
        $this->line("  Special campaign: " . ($noSpecials ? 'SKIP' : "~{$specialPercent}% variant"));
        $this->line("  Truncate trước:   " . ($truncate ? 'YES' : 'no'));

        if (! $this->confirm('Tiến hành?', true)) {
            return self::FAILURE;
        }

        $started = microtime(true);

        // Bước 1: seed N product
        $this->newLine();
        $this->info('━━━ [1/4] products:seed ━━━');
        $exit = Artisan::call('products:seed', array_filter([
            'count'      => $total,
            '--chunk'    => $chunk,
            '--truncate' => $truncate,
        ]), $this->output);
        if ($exit !== self::SUCCESS) {
            $this->error('products:seed lỗi.');
            return $exit;
        }

        // Bước 2: variant cho X% product
        if ($variantPercent > 0) {
            $this->newLine();
            $this->info('━━━ [2/4] variants:seed ━━━');
            $exit = Artisan::call('variants:seed', [
                '--chunk'            => 100,
                '--percent'          => $variantPercent,
                '--min'              => $variantMin,
                '--max'              => $variantMax,
                '--special-percent'  => 0,  // tách bước, dùng specials:seed thuần
                '--truncate'         => $truncate,
            ], $this->output);
            if ($exit !== self::SUCCESS) {
                $this->error('variants:seed lỗi.');
                return $exit;
            }
        } else {
            $this->warn('--variant-percent=0 → skip variants:seed');
        }

        // Bước 3: default variant cho product còn lại (simple)
        $this->newLine();
        $this->info('━━━ [3/4] simple-variants:seed ━━━');
        $exit = Artisan::call('simple-variants:seed', [
            '--chunk' => $chunk,
        ], $this->output);
        if ($exit !== self::SUCCESS) {
            $this->error('simple-variants:seed lỗi.');
            return $exit;
        }

        // Bước 4: campaign giảm giá
        if (! $noSpecials && $specialPercent > 0) {
            $this->newLine();
            $this->info('━━━ [4/4] specials:seed ━━━');
            $exit = Artisan::call('specials:seed', [
                '--percent'  => $specialPercent,
                '--truncate' => $truncate,
            ], $this->output);
            if ($exit !== self::SUCCESS) {
                $this->error('specials:seed lỗi.');
                return $exit;
            }
        } else {
            $this->warn('Skip specials:seed (--no-specials hoặc percent=0)');
        }

        $elapsed = round(microtime(true) - $started, 1);
        $this->newLine();
        $this->info("━━━ XONG TOÀN BỘ trong {$elapsed}s ━━━");

        if (! $noScout) {
            $this->newLine();
            $this->warn('Bước tiếp (nếu dùng Scout/Meilisearch):');
            $this->line('  docker compose exec infun-php php artisan scout:import "App\\Models\\Entities\\Product"');
        }

        return self::SUCCESS;
    }
}
