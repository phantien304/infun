<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Menu;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\MenuRepositoryInterface;

class MenuRepository extends QueryableRepository implements MenuRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return Menu::class;
    }

    public function getMenuByPosition($position = 'top')
    {
        return $this->resetModel()
            ->where('position', $position)
            ->with([
                'menuValues.description'
            ])
            ->get();
    }

    /**
     * Menu cache thực sự ghi ở `App\Http\Supports\MenusClient::getMenus()`
     * với key `getCoreConfig('cache.menu') . locale` — NHƯNG invalidation
     * vẫn thuộc trách nhiệm MenuRepository theo convention "repo sở hữu
     * cache lifecycle của entity của nó", để `CacheFlushObserver` (đăng ký
     * cho Menu + MenuValue trong AppServiceProvider) có 1 chỗ duy nhất gọi.
     *
     * Dùng `forgetSystem` (không phải `forgetCache`) vì MenusClient ghi qua
     * `CacheGate::systemStore()` — bypass-debug-exempt. Nếu xoá qua
     * `forgetCache` (đi qua `store()` thường) → debug=1 sẽ no-op và cache
     * menu cũ vẫn còn.
     */
    public function flushCache(): void
    {
        $this->forgetSystem(getCoreConfig('cache.menu'));
    }
}
