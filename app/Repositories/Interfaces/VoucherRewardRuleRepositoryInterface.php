<?php

namespace App\Repositories\Interfaces;

use App\Models\Entities\VoucherRewardRule;
use App\Repositories\Base\BaseRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

interface VoucherRewardRuleRepositoryInterface extends BaseRepositoryInterface
{
    public function listRunningRewardRules(): Collection;

    public function incrementRewardRuleCount(int $ruleId, int $by = 1): int;

    public function decrementRewardRuleCount(int $ruleId, int $by = 1): void;

    public function flushCache(): void;

    // ----- CMS (admin) -----
    public function listForCms(Request $request): LengthAwarePaginator;

    public function getForCms(int $id): ?VoucherRewardRule;

    public function saveFromCms(?VoucherRewardRule $rule, array $data): VoucherRewardRule;

    public function deleteByIds(array $ids): int;

    public function restoreByIds(array $ids): int;

    public function restoreById(int $id): ?VoucherRewardRule;
}
