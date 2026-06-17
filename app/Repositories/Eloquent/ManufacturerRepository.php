<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Manufacturer;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Concerns\CacheableRepository;
use App\Repositories\Interfaces\ManufacturerRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;

class ManufacturerRepository extends QueryableRepository implements ManufacturerRepositoryInterface
{
    use CacheableRepository;

    public function model(): string
    {
        return Manufacturer::class;
    }

    /**
     * System-wide cache: loaded on every page render via Controller::render
     * (sidebar filter). Uses rememberSystem so it stays cached even when
     * config_debug=1 — avoids 1 SELECT per request in dev mode too.
     */
    public function listAllCached(): Collection
    {
        return $this->rememberSystem(
            setting('cache.manufacturers'),
            fn () => $this->listAll()
        );
    }

    /**
     * Fetch a single manufacturer by id. No description relation exists on
     * the Manufacturer model, so this is a plain find. Returns null for
     * invalid ids or soft-deleted rows.
     */
    public function getManufacturerDetail(int $id): ?Manufacturer
    {
        if ($id <= 0) {
            return null;
        }

        return $this->resetModel()->find($id);
    }

    public function flushCache(): void
    {
        $this->forgetSystem(setting('cache.manufacturers'));
    }
}
