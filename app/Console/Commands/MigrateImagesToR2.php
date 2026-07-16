<?php

namespace App\Console\Commands;

use App\Jobs\ProcessImageUpload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sinh sẵn thumbnail cho ảnh sản phẩm TRÊN R2 (không resize lúc render).
 *
 * Tiền đề: ảnh GỐC đã ở R2 (đã rclone copy). Command gom mọi path ảnh DISTINCT
 * trong DB rồi dispatch ProcessImageUpload cho từng path — job đọc gốc từ R2,
 * sinh các size trang list ('config/media.php') và ghi lại lên R2.
 *
 * Nguồn path (mọi bảng có cột ảnh được render qua thumbnail()):
 *   - product.image          ảnh chính card list
 *   - product_image.image    gallery (gồm cả gallery gắn variant)
 *   - product_variant.image  swatch/ảnh variant (variantSwatchImages,
 *                            variant_image trong ProductOptionService, cart)
 *   - option_value.image     swatch fallback khi variant không có ảnh riêng
 *                            ($ov->image trong buildImageAndOptionValues, cart)
 *
 * Dedupe theo path vì seed thường tái dùng chung một pool ảnh → tránh sinh
 * lại cùng thumbnail hàng nghìn lần. Job idempotent (bỏ qua nếu thumb đã có).
 *
 * YÊU CẦU: disk đích phải trỏ R2 — đặt IMAGE_DISK_DRIVER=s3 + AWS_* khi chạy.
 *
 *   php artisan images:migrate-r2 --dry-run     # đếm trước
 *   php artisan images:migrate-r2 --sync        # chạy inline, không cần worker
 *   php artisan images:migrate-r2               # đẩy vào queue (nhớ queue:work)
 */
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

    /** @return \Illuminate\Support\Collection<int, string> */
    private function distinctImages(string $table): \Illuminate\Support\Collection
    {
        return DB::table($table)
            ->whereNotNull('image')
            ->where('image', '!=', '')
            ->distinct()
            ->pluck('image');
    }
}
