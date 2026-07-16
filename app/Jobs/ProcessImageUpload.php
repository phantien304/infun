<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

/**
 * Đẩy ảnh gốc lên R2 (disk 'image') và SINH SẴN các thumbnail trang list cần,
 * lưu vào đúng cache-path mà MyStorage::resizeImage() tra cứu
 * ('{folder_cache}/{w}x{h}/{path}') → request chỉ trả URL, KHÔNG resize GD.
 *
 * Chạy trong queue worker nên GD (nạp bitmap) không còn nằm trong web request.
 *
 * Dispatch sau khi đã lưu ảnh gốc vào một disk tạm:
 *   ProcessImageUpload::dispatch('catalog/products/foo.jpg', 'tmp');
 */
class ProcessImageUpload implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Thử lại tối đa 3 lần, giãn 10s/30s/60s. */
    public int $tries = 3;
    public array $backoff = [10, 30, 60];
    public int $timeout = 120;

    /**
     * @param string $path       Relative path DÙNG CHUNG cho gốc lẫn thumbnail
     *                           (vd 'catalog/products/foo.jpg'). KHÔNG chứa domain.
     * @param string $sourceDisk Disk chứa ảnh gốc vừa upload (vd 'tmp' hoặc 'public').
     * @param string $targetDisk Disk đích — R2 (mặc định 'image').
     */
    public function __construct(
        public string $path,
        public string $sourceDisk = 'tmp',
        public string $targetDisk = 'image',
    ) {
    }

    public function handle(): void
    {
        $path   = ltrim(str_replace('\\', '/', $this->path), '/');
        $source = Storage::disk($this->sourceDisk);
        $target = Storage::disk($this->targetDisk);

        if (! $source->exists($path)) {
            logError("[ProcessImageUpload] source missing: {$this->sourceDisk}:{$path}");

            return;
        }

        // Đọc gốc 1 lần vào bộ nhớ worker.
        $bytes = $source->get($path);

        // 1) Đẩy ảnh GỐC lên R2 (idempotent). KHÔNG set visibility 'public' vì
        //    R2 không dùng ACL per-object — public là ở tầng bucket/custom domain.
        if (! $target->exists($path)) {
            $target->put($path, $bytes);
        }

        // 2) Sinh sẵn thumbnail vào đúng cache-path resizeImage() tra cứu.
        $folderCache = trim((string) (setting('folder_cache') ?: 'cache'), '/');

        foreach ($this->sizes() as [$width, $height]) {
            $cachePath = "{$folderCache}/{$width}x{$height}/{$path}";

            if ($target->exists($cachePath)) {
                continue; // đã có → bỏ qua (idempotent)
            }

            try {
                // Đọc lại từ $bytes mỗi size vì coverDown() mutate ảnh.
                $image = ImageManager::gd()->read($bytes);
                $image->coverDown($width, $height);
                $target->put($cachePath, (string) $image->encodeByPath($cachePath));
            } catch (\Throwable $e) {
                // Một size lỗi không được làm hỏng cả job / mất ảnh gốc.
                logError("[ProcessImageUpload] resize {$width}x{$height} {$path}: {$e->getMessage()}");
            }
        }

        // 3) Dọn ảnh gốc tạm (chỉ khi nguồn là disk tạm).
        if ($this->sourceDisk === 'tmp') {
            $source->delete($path);
        }
    }

    /**
     * Các size trang list + social cần sinh sẵn. Ưu tiên 300x300 (card list).
     * Có thể override bằng config('media.thumbnail_sizes').
     *
     * @return array<int, array{0:int, 1:int}>
     */
    protected function sizes(): array
    {
        return config('media.thumbnail_sizes', [
            [300, 300], // card trang list (_product.blade.php)
            [50, 50],   // sidebar
            [800, 354], // og:image / social share
        ]);
    }

    public function failed(\Throwable $e): void
    {
        logError("[ProcessImageUpload] FAILED {$this->path}: {$e->getMessage()}");
    }
}
