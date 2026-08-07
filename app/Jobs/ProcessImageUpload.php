<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

class ProcessImageUpload implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public array $backoff = [10, 30, 60];
    public int $timeout = 120;

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

        $bytes = $source->get($path);

        if (! $target->exists($path)) {
            $target->put($path, $bytes);
        }

        $folderCache = trim((string) (setting('folder_cache') ?: 'cache'), '/');
        foreach ($this->sizes() as [$width, $height]) {
            $cachePath = "{$folderCache}/{$width}x{$height}/{$path}";

            if ($target->exists($cachePath)) {
                continue;
            }

            try {
                $image = ImageManager::gd()->read($bytes);
                $image->coverDown($width, $height);
                $target->put($cachePath, (string) $image->encodeByPath($cachePath));
            } catch (\Throwable $e) {
                logError("[ProcessImageUpload] resize {$width}x{$height} {$path}: {$e->getMessage()}");
            }
        }

        if ($this->sourceDisk === 'tmp') {
            $source->delete($path);
        }
    }

    protected function sizes(): array
    {
        return config('media.thumbnail_sizes', [[300, 300], [50, 50],[400,400],[800, 354]]);
    }

    public function failed(\Throwable $e): void
    {
        logError("[ProcessImageUpload] FAILED {$this->path}: {$e->getMessage()}");
    }
}
