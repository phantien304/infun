<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\Information;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\InformationRepositoryInterface;

/**
 * Trang nội dung tĩnh (giới thiệu / chính sách / điều khoản...). Detail page
 * mirror pattern `BlogRepository` / `StoreReviewRepository`: eager-load
 * `description` (đã tự `->forLocale()`), KHÔNG cache để view counter
 * (`increment('viewed')`) luôn fresh — đồng nhất với 2 detail repo kia.
 */
class InformationRepository extends QueryableRepository implements InformationRepositoryInterface
{
    public function model(): string
    {
        return Information::class;
    }

    public function getDetail($id): ?Information
    {
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }

        return $this->resetModel()
            ->with('description')
            ->find($id);
    }
}
