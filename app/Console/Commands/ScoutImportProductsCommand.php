<?php

namespace App\Console\Commands;

use App\Models\Entities\Product;
use Illuminate\Console\Command;

/**
 * Import Product lên Meilisearch cho MỌI locale (index per-locale).
 *
 * `Product::searchableAs()` = `products_{locale}` → `scout:import` gốc chỉ đẩy
 * index của locale đang active (APP_LOCALE). Command này loop
 * `config('app.locales')`, setLocale rồi delegate `scout:flush`/`scout:import`
 * cho từng index.
 *
 * Cách dùng (thứ tự chuẩn khi đổi schema index):
 *   php artisan config:clear
 *   php artisan scout:sync-index-settings      # push settings MỌI index per-locale
 *   php artisan products:scout-import          # import mọi locale
 *   php artisan products:scout-import --locale=vi --locale=en
 *   php artisan products:scout-import --fresh  # scout:flush trước khi import
 *
 * Lưu ý:
 * - Chạy với SCOUT_QUEUE=false (default) — nếu queue, job chạy ở worker có
 *   locale RIÊNG → doc rơi sai index.
 * - 500k doc/locale mất 5-15 phút (chunk SCOUT_CHUNK_SEARCHABLE=500). Nhiều
 *   locale + sợ OOM (split-process pattern trong CLAUDE.md) → chạy từng
 *   process riêng bằng --locale=X.
 */
class ScoutImportProductsCommand extends Command
{
    protected $signature = 'products:scout-import
        {--locale=* : Chỉ import các locale này (mặc định: mọi locale trong APP_LOCALES)}
        {--fresh    : scout:flush index trước khi import (dọn doc mồ côi)}';

    protected $description = 'Import Product lên Meilisearch cho mọi locale (index per-locale products_{locale})';

    public function handle(): int
    {
        $all       = (array) config('app.locales', [config('app.locale')]);
        $requested = array_values(array_filter((array) $this->option('locale')));
        $locales   = empty($requested) ? $all : array_values(array_intersect($all, $requested));

        if (empty($locales)) {
            $this->error('Không có locale hợp lệ. APP_LOCALES = ' . implode(',', $all));

            return self::FAILURE;
        }

        if (config('scout.driver') !== 'meilisearch') {
            $this->warn('SCOUT_DRIVER != meilisearch — vẫn chạy, nhưng kiểm tra lại .env nếu không chủ đích.');
        }

        $original = app()->getLocale();
        $started  = microtime(true);
        $total    = count($locales);

        try {
            foreach ($locales as $i => $locale) {
                app()->setLocale($locale);
                $index = (new Product())->searchableAs();
                $step  = ($i + 1) . '/' . $total;

                $this->newLine();
                $this->info("━━━ [{$step}] locale={$locale} → index `{$index}` ━━━");

                if ($this->option('fresh')) {
                    $exit = $this->call('scout:flush', ['model' => Product::class]);
                    if ($exit !== self::SUCCESS) {
                        $this->error("scout:flush lỗi (locale={$locale}).");

                        return $exit;
                    }
                }

                $exit = $this->call('scout:import', ['model' => Product::class]);
                if ($exit !== self::SUCCESS) {
                    $this->error("scout:import lỗi (locale={$locale}).");

                    return $exit;
                }
            }
        } finally {
            app()->setLocale($original);
        }

        $elapsed = round(microtime(true) - $started, 1);
        $this->newLine();
        $this->info("━━━ XONG {$total} locale trong {$elapsed}s ━━━");
        $this->line('Verify: curl -s "$MEILISEARCH_HOST/indexes" -H "Authorization: Bearer $MEILISEARCH_KEY" | jq \'.results[].uid\'');

        return self::SUCCESS;
    }
}
