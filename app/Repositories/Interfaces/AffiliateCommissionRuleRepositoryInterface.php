<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\AffiliateCommissionRule;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

interface AffiliateCommissionRuleRepositoryInterface extends BaseRepositoryInterface
{
    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?AffiliateCommissionRule;

    public function saveFromCms(?AffiliateCommissionRule $rule, array $data): AffiliateCommissionRule;

    public function deleteByIds(array $ids): int;
}
