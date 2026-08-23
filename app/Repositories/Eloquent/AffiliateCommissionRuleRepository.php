<?php

namespace App\Repositories\Eloquent;

use App\Models\Entities\AffiliateCommissionRule;
use App\Repositories\Base\QueryableRepository;
use App\Repositories\Interfaces\AffiliateCommissionRuleRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

/**
 * Rate hoa hồng theo ngành hàng (`affiliate_commission_rule`).
 *
 * Thứ tự ưu tiên khi tính hoa hồng (AffiliateConversionService):
 *   affiliate.commission_rate (rate riêng KOL, phẳng cả đơn)
 *     > rule theo category của SP (nhiều category có rule → lấy CAO NHẤT)
 *       > config_affiliate_commission_rate (global)
 *
 * UNIQUE category_id ở DB: mỗi ngành hàng đúng một rule.
 *
 * KHÔNG soft delete — bảng cấu hình thuần, xoá là xoá; rate đã áp cho đơn cũ
 * nằm ở snapshot `affiliate_conversion.commission_rate` nên không mất dấu.
 */
class AffiliateCommissionRuleRepository extends QueryableRepository implements AffiliateCommissionRuleRepositoryInterface
{
    public function model(): string
    {
        return AffiliateCommissionRule::class;
    }

    public function listForCms(Request $request): LengthAwarePaginator
    {
        $sortable = ['id', 'rate', 'created_at'];
        $sort     = in_array($request->input('sort'), $sortable, true) ? $request->input('sort') : 'id';
        $order    = strtolower((string) $request->input('order', 'desc')) === 'asc' ? 'asc' : 'desc';
        $perPage  = max(1, (int) $request->input('per_page', 50));

        return $this->resetModel()->newQuery()
            ->with(['category.description'])
            ->orderBy($sort, $order)
            ->paginate($perPage);
    }

    public function getForCms(int $id): ?AffiliateCommissionRule
    {
        return $this->resetModel()->with(['category.description'])->find($id);
    }

    public function saveFromCms(?AffiliateCommissionRule $rule, array $data): AffiliateCommissionRule
    {
        $rule ??= new AffiliateCommissionRule();
        $rule->category_id = (int) $data['category_id'];
        $rule->rate = $data['rate'];
        $rule->save();

        return $rule->load(['category.description']);
    }

    public function deleteByIds(array $ids): int
    {
        return $this->resetModel()->newQuery()->whereIn('id', $ids)->delete();
    }
}
