<?php

namespace App\Console\Commands;

use App\Jobs\ProcessImageUpload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateImagesToR2 extends Command
{
    protected $signature = 'images:migrate-r2
        {--disk=image : Disk đích (R2)}
        {--source= : Disk đọc ảnh gốc (mặc định = --disk, vì gốc đã ở R2)}
        {--sync : Chạy job inline thay vì đẩy queue}
        {--dry-run : Chỉ liệt kê, không dispatch}';

    protected $description = 'Sinh thumbnail cho ảnh sản phẩm trên R2 theo từng path distinct.';

    public function handle(): int
    {
        $targetDisk = (string) $this->option('disk');
        $sourceDisk = (string) ($this->option('source') ?: $targetDisk);
        $sync       = (bool) $this->option('sync');
        $dryRun     = (bool) $this->option('dry-run');
        $paths = collect()
            ->merge($this->distinctImages('product'))
            ->merge($this->distinctImages('product_image'))
            ->merge($this->distinctImages('product_variant'))
            ->merge($this->distinctImages('option_value'))
            ->merge($this->distinctImages('blog'))
            ->merge($this->distinctImages('banner_value'))
            ->map(fn ($p) => ltrim(str_replace('\\', '/', (string) $p), '/'))
            ->filter(fn ($p) => $p !== '' && ! str_starts_with($p, 'http'))
            ->unique()
            ->values();

        $this->info("Ảnh distinct: {$paths->count()}  |  source={$sourceDisk}  target={$targetDisk}");

        if ($dryRun) {
            $paths->take(30)->each(fn ($p) => $this->line("  {$p}"));
            $this->comment('(dry-run — chưa dispatch)');

            return self::SUCCESS;
        }

        $bar = $this->output->createProgressBar($paths->count());
        $bar->start();
        foreach ($paths as $path) {
            $sync
                ? ProcessImageUpload::dispatchSync($path, $sourceDisk, $targetDisk)
                : ProcessImageUpload::dispatch($path, $sourceDisk, $targetDisk);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info($sync
            ? 'Xong — thumbnail đã sinh xong trên R2.'
            : 'Đã đẩy job vào queue. Chạy: php artisan queue:work');

        return self::SUCCESS;
    }

    private function distinctImages(string $table): \Illuminate\Support\Collection
    {
        return DB::table($table)
            ->whereNotNull('image')
            ->where('image', '!=', '')
            ->distinct()
            ->pluck('image');
    }
}
