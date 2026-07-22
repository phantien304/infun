<?php

namespace App\Observers;

use App\Helpers\CacheGate;

class CacheFlushObserver
{
    public function __construct(protected array $repoInterfaces)
    {
    }

    public function saved($model): void
    {
        $this->flush();
    }

    public function deleted($model): void
    {
        $this->flush();
    }

    public function restored($model): void
    {
        $this->flush();
    }

    public function forceDeleted($model): void
    {
        $this->flush();
    }

    protected function flush(): void
    {
        foreach ($this->repoInterfaces as $iface) {
            try {
                app($iface)->flushCache();
            } catch (\Throwable $e) {
                logError('CacheFlushObserver flush ' . $iface . ': ' . $e->getMessage());
            }
        }

        // Full-page cache (CachePage) giờ cache cả trang danh mục/list/phân trang
        // → phải flush khi data nguồn đổi, nếu không stale tới hết TTL 24h. Tag
        // flush trên redis rẻ (bump version), no-op trên file driver. Chỉ chạy
        // cho model có trong $cacheMap nên tần suất = nhịp CMS mutate, chấp nhận
        // trade-off "1 write xoá toàn page cache" đổi lấy list luôn tươi.
        CacheGate::flushPages();
    }
}
