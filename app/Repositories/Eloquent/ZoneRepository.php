<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Zone;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\ZoneRepositoryInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ZoneRepository extends QueryableRepository implements ZoneRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return Zone::class;
    }

    protected function baseQuery(): Builder
    {
        return $this->model->newQuery()
            ->where('country_id', getCoreConfig('zones.country_id_default'));
    }

    protected function withRelations(): array
    {
        return ['description'];
    }

    /**
     * Zones load mọi page render (dropdown chọn tỉnh ở checkout + popup địa
     * chỉ) → `rememberSystem` luôn cache.
     *
     * `rememberSystem` mặc định `perLocale = true` đã tự append locale vào
     * cuối key — không cần concat thủ công ở đây (double append → key
     * `zones_vivi` thay vì `zones_vi`).
     */
    public function listAllCached(): Collection
    {
        return $this->rememberSystem(
            setting('cache.zones'),
            fn () => $this->listAll()
        );
    }

    public function flushCache(): void
    {
        $this->forgetSystem(setting('cache.zones'));
    }
}
