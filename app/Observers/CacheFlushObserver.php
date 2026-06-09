<?php

namespace App\Observers;

/**
 * Observer generic — flush 1 hoặc nhiều repository cache mỗi khi model
 * gắn observer này có event save/delete/restore.
 *
 * Thay cho 11 file observer trivial (BlogCategoryObserver, ProductObserver,
 * CategoryObserver, ...) cùng pattern `extend abstract → override
 * repoInterface()`. 1 class duy nhất nhận list interface qua constructor,
 * mapping tập trung ở `AppServiceProvider::registerObservers()`.
 *
 * Tại sao bỏ pattern abstract cũ:
 *  - 11 file × ~13-30 dòng = ~200 dòng boilerplate cho cùng 1 ý "model X
 *    → flush repo Y".
 *  - Mapping rải khắp Observers/ — muốn biết "Category save thì flush gì"
 *    phải mở CategoryObserver, đọc override doFlush, dò 2 interface.
 *  - Generic version: 1 file 40 dòng, mapping table 1 chỗ → grep 1 lần.
 *
 * Cross-flush (vd Category đổi → cả categories cache + product cache stale
 * vì card hiển thị tên category) chỉ cần khai báo `[CategoryRepoInterface,
 * ProductRepoInterface]` trong map, KHÔNG cần subclass override.
 *
 * Custom logic phức tạp (vd `ReviewObserver` cập nhật aggregate, hay
 * `SettingObserver` đặc biệt gọi `CacheGate::flushAll`) GIỮ làm file riêng
 * — generic này không cover được.
 */
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
    }
}
