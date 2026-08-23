<?php

namespace App\Http\Controllers\Api\Cms\Affiliate;

use App\Data\Cms\AffiliateCommissionRuleData;
use App\Http\Controllers\Api\Cms\BaseCmsController;
use App\Http\Requests\Cms\AffiliateCommissionRuleRequest;
use App\Models\Entities\AffiliateCommissionRule;
use App\Repositories\Interfaces\AffiliateCommissionRuleRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

/**
 * CRUD rate hoa hồng theo ngành hàng.
 *
 * Không dùng `cmsApiResource`: bảng KHÔNG có soft delete nên không có
 * restore, và bulk restore/delete cho một bảng cấu hình vài chục dòng là
 * thừa. Khai tay đúng 5 route thật sự dùng.
 */
class AffiliateCommissionRuleController extends BaseCmsController
{
    protected string $permission = 'affiliate-commission-rule';

    public function __construct(
        private readonly AffiliateCommissionRuleRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        return AffiliateCommissionRuleData::collect(
            $this->repo->listForCms($request),
            PaginatedDataCollection::class,
        );
    }

    public function show($id)
    {
        $rule = $this->repo->getForCms((int) $id);
        abort_if($rule === null, 404);

        return respondSuccess(AffiliateCommissionRuleData::fromModel($rule));
    }

    public function store(AffiliateCommissionRuleRequest $request)
    {
        $rule = $this->repo->saveFromCms(null, $request->validated());

        return respondCreated(AffiliateCommissionRuleData::fromModel($rule), 'affiliate_rule_created');
    }

    public function update(AffiliateCommissionRuleRequest $request, AffiliateCommissionRule $affiliateCommissionRule)
    {
        $rule = $this->repo->saveFromCms($affiliateCommissionRule, $request->validated());

        return respondSuccess(AffiliateCommissionRuleData::fromModel($rule), 'affiliate_rule_updated');
    }

    public function destroy(AffiliateCommissionRule $affiliateCommissionRule)
    {
        $this->repo->deleteByIds([$affiliateCommissionRule->id]);

        return response()->noContent();
    }
}
