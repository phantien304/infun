<?php

namespace App\Http\Controllers\Api\Cms\Marketing;

use App\Data\Cms\VoucherRewardRuleData;
use App\Http\Controllers\Api\Cms\BaseCmsController;
use App\Http\Requests\Cms\VoucherRewardRuleRequest;
use App\Models\Entities\VoucherRewardRule;
use App\Repositories\Interfaces\VoucherRewardRuleRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

/**
 * CMS quy tắc tặng voucher theo giá trị đơn — MÀN MỚI, mt219 không có.
 *
 * Chỉ quản lý chương trình; việc PHÁT thưởng do
 * `OrderVoucherRewardObserver` + `VoucherRewardService` lo khi đơn chuyển
 * sang trạng thái hoàn tất. Biên bản phát (`voucher_reward_grant`) chỉ đọc.
 */
class VoucherRewardRuleController extends BaseCmsController
{
    protected string $permission = 'voucher-reward-rule';

    public function __construct(
        private readonly VoucherRewardRuleRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        return VoucherRewardRuleData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
    }

    public function store(VoucherRewardRuleRequest $request)
    {
        $rule = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(VoucherRewardRuleData::fromModel($rule), 'voucher_reward_rule_created');
    }

    public function show($id)
    {
        $rule = $this->repo->getForCms((int) $id);
        abort_if($rule === null, 404);

        return respondSuccess(VoucherRewardRuleData::fromModel($rule));
    }

    public function update(VoucherRewardRuleRequest $request, VoucherRewardRule $voucherRewardRule)
    {
        $rule = $this->repo->saveFromCms($voucherRewardRule, $request->validated());

        return respondSuccess(VoucherRewardRuleData::fromModel($rule), 'voucher_reward_rule_updated');
    }

    public function destroy(VoucherRewardRule $voucherRewardRule)
    {
        $this->repo->deleteByIds([$voucherRewardRule->id]);

        return response()->noContent();
    }

    public function restore($id)
    {
        $rule = $this->repo->restoreById((int) $id);
        abort_if($rule === null, 404);

        return respondSuccess(VoucherRewardRuleData::fromModel($rule), 'voucher_reward_rule_restored');
    }

    public function bulk(Request $request)
    {
        $data = $request->validate([
            'action' => 'required|in:delete,restore',
            'ids'    => 'required|array|min:1',
            'ids.*'  => 'integer',
        ]);

        $affected = $data['action'] === 'delete'
            ? $this->repo->deleteByIds($data['ids'])
            : $this->repo->restoreByIds($data['ids']);

        return respondSuccess(['affected' => $affected]);
    }
}
