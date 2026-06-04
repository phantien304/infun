<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Carrier;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\CarrierRepositoryInterface;
use Illuminate\Support\Collection;

class CarrierRepository extends QueryableRepository implements CarrierRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return Carrier::class;
    }

    /**
     * Danh sách carrier active sắp theo sort_order DESC — dùng ở trang checkout
     * để render radio chọn nhà vận chuyển. Cache thường, per-locale (carrier
     * không có description i18n hiện tại; per-locale chỉ để đồng nhất key).
     */
    public function listAllCached(): Collection
    {
        return $this->rememberCache(
            setting('cache.carriers'),
            fn () => $this->resetModel()
                ->orderBy('sort_order', 'DESC')
                ->orderBy('id', 'DESC')
                ->get()
        );
    }
}
