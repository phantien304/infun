<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seed bảng `product_variant_special` — campaign giảm giá ở variant level
 * (cluster Hướng B, xem CLAUDE.md mục "Cluster variant special").
 *
 * Chạy SAU variants:seed — cần product_variant đã tồn tại. Pick % variant
 * (default 30%) gắn 1 special đang active. Hỗ trợ thêm % variant có
 * campaign sắp diễn ra (future) hoặc đã kết thúc (expired) để test toán tử
 * date so sánh + auto-recompute aggregate khi schedule chạy.
 *
 * Cách dùng:
 *   php artisan specials:seed                        # mặc định 30% active
 *   php artisan specials:seed --percent=50
 *   php artisan specials:seed --percent=40 --future=10 --expired=5
 *   php artisan specials:seed --truncate             # xoá special cũ trước
 *
 * Sau khi seed:
 *   php artisan cache:clear
 *   (Cache product detail key theo getUserGroupId — flush để giá mới hiện)
 */
class SeedVariantSpecialsCommand extends Command
{
    protected $signature = 'specials:seed
        {--chunk=500 : Số variant mỗi batch xử lý}
        {--percent=30 : % variant nhận campaign đang active}
        {--future=0 : % variant nhận campaign tương lai (date_start > now)}
        {--expired=0 : % variant nhận campaign đã hết hạn (date_end < now)}
        {--user-group=1 : user_group_id áp campaign}
        {--truncate : Xoá toàn bộ product_variant_special trước khi seed}';

    protected $description = 'Seed product_variant_special — campaign giảm giá per-variant cho test schema mới (Hướng B)';

    public function handle(): int
    {
        if (! Schema::hasTable('product_variant_special')) {
            $this->error('Bảng product_variant_special chưa tồn tại — chạy migration trước.');

            return self::FAILURE;
        }

        $chunk      = max(50, (int) $this->option('chunk'));
        $pActive    = max(0, min(100, (int) $this->option('percent')));
        $pFuture    = max(0, min(100, (int) $this->option('future')));
        $pExpired   = max(0, min(100, (int) $this->option('expired')));
        $userGroup  = (int) $this->option('user-group');
        $truncate   = (bool) $this->option('truncate');

        if ($pActive + $pFuture + $pExpired === 0) {
            $this->warn('Tất cả percent = 0 — không có gì để seed.');

            return self::SUCCESS;
        }

        if ($truncate
            && ! $this->confirm('Xoá TOÀN BỘ product_variant_special trước khi seed?', true)) {
            return self::FAILURE;
        }

        DB::disableQueryLog();
        DB::statement('SET unique_checks=0');
        DB::statement('SET foreign_key_checks=0');
        $started = microtime(true);

        try {
            if ($truncate) {
                DB::table('product_variant_special')->truncate();
                $this->line('  truncated product_variant_special');
            }

            $totalVariants = (int) DB::table('product_variant')
                ->whereNull('deleted_at')
                ->count();
            if ($totalVariants === 0) {
                $this->warn('Bảng product_variant rỗng — chạy `php artisan variants:seed` trước.');

                return self::FAILURE;
            }

            $this->info("Tổng {$totalVariants} variant. Active: {$pActive}%, future: {$pFuture}%, expired: {$pExpired}%.");

            // Explicit-id strategy: lock MAX id để bulk insert không
            // insertGetId từng row.
            $nextId = ((int) DB::table('product_variant_special')->max('id')) + 1;

            $bar = $this->output->createProgressBar($totalVariants);
            $bar->start();

            $totalCreated = ['active' => 0, 'future' => 0, 'expired' => 0];

            DB::table('product_variant')
                ->whereNull('deleted_at')
                ->select('id', 'product_id', 'price')
                ->orderBy('id')
                ->chunk($chunk, function ($variants) use (
                    $pActive,
                    $pFuture,
                    $pExpired,
                    $userGroup,
                    &$nextId,
                    &$totalCreated,
                    $bar,
                ) {
                    [$rows, $counts] = $this->buildBatch(
                        $variants,
                        $pActive,
                        $pFuture,
                        $pExpired,
                        $userGroup,
                        $nextId,
                    );

                    if ($rows) {
                        DB::table('product_variant_special')->insert($rows);
                        $nextId += count($rows);
                    }
                    foreach ($counts as $k => $v) {
                        $totalCreated[$k] += $v;
                    }

                    $bar->advance(count($variants));
                });

            $bar->finish();
            $this->newLine();

            // Reset AUTO_INCREMENT để insert thật sau này không collide.
            DB::statement("ALTER TABLE product_variant_special AUTO_INCREMENT = {$nextId}");
        } finally {
            DB::statement('SET unique_checks=1');
            DB::statement('SET foreign_key_checks=1');
        }

        $elapsed = round(microtime(true) - $started, 2);
        $total = array_sum($totalCreated);
        $this->info("Xong: {$total} variant_special trong {$elapsed}s (~"
            . round($total / max($elapsed, 0.01)) . " row/s)");
        $this->line("  active:  {$totalCreated['active']}");
        $this->line("  future:  {$totalCreated['future']}");
        $this->line("  expired: {$totalCreated['expired']}");
        $this->info('Khuyến nghị: php artisan cache:clear && ANALYZE TABLE product_variant_special;');

        return self::SUCCESS;
    }

    /**
     * Build payload cho 1 chunk variant. Mỗi variant chỉ nhận TỐI ĐA 1 row
     * trong các phân loại (active / future / expired) — pick theo thứ tự
     * ưu tiên (active > future > expired) để tránh duplicate hard.
     *
     * @return array{0:array, 1:array{active:int, future:int, expired:int}}
     */
    private function buildBatch(
        $variants,
        int $pActive,
        int $pFuture,
        int $pExpired,
        int $userGroup,
        int $startId,
    ): array {
        $now = Carbon::now();
        $rows = [];
        $counts = ['active' => 0, 'future' => 0, 'expired' => 0];
        $id = $startId;

        foreach ($variants as $variant) {
            $kind = $this->pickKind($pActive, $pFuture, $pExpired);
            if ($kind === null) {
                continue;
            }

            [$dateStart, $dateEnd] = $this->dateRangeFor($kind, $now);

            // Giá special: 50–85% của variant.price (giảm 15–50%). Clamp
            // floor 1000đ chống làm tròn về 0 trên giá thấp.
            $basePrice = (float) $variant->price;
            $specialPrice = max(1000, (int) round($basePrice * (rand(50, 85) / 100)));

            $rows[] = [
                'id'                 => $id++,
                'product_variant_id' => $variant->id,
                'product_id'         => $variant->product_id,
                'user_group_id'      => $userGroup,
                // priority random — test logic "highest wins" khi nhiều
                // overlap. Range 0–10 đủ rộng cho test, không quá lệch.
                'priority'           => rand(0, 10),
                'price'              => $specialPrice,
                'date_start'         => $dateStart,
                'date_end'           => $dateEnd,
                'created_at'         => $now,
                'updated_at'         => $now,
                'deleted_at'         => null,
            ];

            $counts[$kind]++;
        }

        return [$rows, $counts];
    }

    /**
     * Quyết định variant này có nhận campaign không và thuộc kind nào.
     * Roll 1 lần qua [0..100): 0..pActive → active; tiếp pFuture → future;
     * tiếp pExpired → expired; còn lại → null. Đảm bảo tổng các phần trăm
     * không vượt 100 mới đúng phân phối; nếu vượt, các kind sau bị clip.
     */
    private function pickKind(int $pActive, int $pFuture, int $pExpired): ?string
    {
        $roll = rand(1, 100);
        if ($roll <= $pActive) {
            return 'active';
        }
        if ($roll <= $pActive + $pFuture) {
            return 'future';
        }
        if ($roll <= $pActive + $pFuture + $pExpired) {
            return 'expired';
        }

        return null;
    }

    /**
     * Sinh khoảng date_start / date_end cho từng kind:
     *  - active:  date_start trong [now - 7d, now - 1h], date_end trong [now + 1d, now + 30d]
     *  - future:  date_start trong [now + 1d, now + 14d], date_end = date_start + 7..30d
     *  - expired: date_start trong [now - 60d, now - 30d], date_end trong [now - 29d, now - 1d]
     *
     * Mục đích: cover đủ case test toán tử so sánh trong scope
     * `dateStartToEnd` + nhánh variant_special của effectivePriceExpression.
     */
    private function dateRangeFor(string $kind, Carbon $now): array
    {
        return match ($kind) {
            'active' => [
                $now->copy()->subMinutes(rand(60, 7 * 24 * 60)),
                $now->copy()->addDays(rand(1, 30)),
            ],
            'future' => (function () use ($now) {
                $start = $now->copy()->addDays(rand(1, 14));
                $end   = $start->copy()->addDays(rand(7, 30));

                return [$start, $end];
            })(),
            'expired' => [
                $now->copy()->subDays(rand(30, 60)),
                $now->copy()->subDays(rand(1, 29)),
            ],
        };
    }
}
