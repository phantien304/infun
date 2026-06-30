<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

/**
 * Wrapper chia seed N review thành nhiều process độc lập — KHÔNG OOM dù
 * dataset lớn (2M+). Mỗi process exit sau khi xong batch → PHP free memory
 * tận gốc, vượt qua mọi leak nội bộ framework/PDO/Carbon.
 *
 * Vì sao cần?
 * -----------
 * reviews:seed chạy 1 process duy nhất. Memory tích luỹ ~4-5 KB/review từ
 * framework static state, Carbon, PDO buffer — không free được bằng
 * unset/gc/disconnect. Ở 900k review chạm 4GB → OOM.
 *
 * Split-run: 10 process × 200k → mỗi process die ở dưới 1.5GB.
 *
 * Cách dùng:
 *   php artisan reviews:seed-bulk 2000000                    # mặc định 200k/process
 *   php artisan reviews:seed-bulk 2000000 --per-run=100000   # nhỏ hơn nếu OOM
 *   php artisan reviews:seed-bulk 2000000 --no-helpful --truncate
 *
 * Tự động:
 *  - Process đầu tiên nhận --truncate (nếu user truyền)
 *  - Tất cả process trung gian: --no-aggregate (skip UPDATE)
 *  - Sau khi tất cả xong: gọi reviews:rebuild-aggregate 1 lần
 */
class SeedReviewsBulkCommand extends Command
{
    protected $signature = 'reviews:seed-bulk
        {count : Tổng số review cần seed}
        {--per-run=200000 : Số review mỗi process con (giảm xuống nếu OOM)}
        {--chunk=200 : Chunk size cho reviews:seed (truyền vào mỗi process)}
        {--truncate : Truncate cluster review trước process đầu tiên}
        {--no-media : Không sinh review_media}
        {--no-helpful : Không sinh review_helpful (giảm 50% row con, ít OOM hơn)}
        {--no-tag : Không sinh review_tag_pivot}
        {--memory=4G : memory_limit cho mỗi process con}';

    protected $description = 'Split seed N review thành nhiều process độc lập — chống OOM tận gốc cho dataset 1M+';

    public function handle(): int
    {
        $total      = max(1, (int) $this->argument('count'));
        $perRun     = max(10_000, (int) $this->option('per-run'));
        $chunk      = max(50, (int) $this->option('chunk'));
        $truncate   = (bool) $this->option('truncate');
        $noMedia    = (bool) $this->option('no-media');
        $noHelpful  = (bool) $this->option('no-helpful');
        $noTag      = (bool) $this->option('no-tag');
        $memoryLim  = (string) $this->option('memory');

        $runs = (int) ceil($total / $perRun);
        $this->info("Plan: seed {$total} review qua {$runs} process × {$perRun}/process (chunk={$chunk}, memory={$memoryLim})");

        if (! $this->confirm('Tiến hành?', true)) {
            return self::FAILURE;
        }

        $started = microtime(true);

        for ($i = 0; $i < $runs; $i++) {
            $thisCount = min($perRun, $total - $i * $perRun);
            $this->newLine();
            $this->info("━━━ Process " . ($i + 1) . "/{$runs} — seed {$thisCount} review ━━━");

            $args = [
                'php',
                "-d", "memory_limit={$memoryLim}",
                'artisan', 'reviews:seed', (string) $thisCount,
                "--chunk={$chunk}",
                '--no-aggregate',  // skip UPDATE — chỉ chạy ở cuối
                '--no-interaction',
            ];

            // Truncate CHỈ process đầu
            if ($truncate && $i === 0) {
                $args[] = '--truncate';
            }
            if ($noMedia) {
                $args[] = '--no-media';
            }
            if ($noHelpful) {
                $args[] = '--no-helpful';
            }
            if ($noTag) {
                $args[] = '--no-tag';
            }

            // Process inheriting stdin/stdout cho thấy progress bar.
            $process = new Process($args, base_path());
            $process->setTimeout(null);
            $process->setTty(Process::isTtySupported());
            $exit = $process->run(function ($type, $buffer) {
                $this->output->write($buffer);
            });

            if ($exit !== 0) {
                $this->error("Process " . ($i + 1) . " thất bại (exit code {$exit}). Dừng.");
                return self::FAILURE;
            }
        }

        // Cuối cùng: rebuild aggregate 1 lần
        $this->newLine();
        $this->info('━━━ Rebuild aggregate ━━━');
        $this->call('reviews:rebuild-aggregate');

        $elapsed = round(microtime(true) - $started, 1);
        $this->newLine();
        $this->info("━━━ XONG: seed {$total} review trong {$elapsed}s ({$runs} process) ━━━");

        return self::SUCCESS;
    }
}
