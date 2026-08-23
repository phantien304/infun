<?php

namespace App\Http\Controllers\Api\Cms\Affiliate;

use App\Data\Cms\AffiliateData;
use App\Enums\AffiliateStatus;
use App\Http\Controllers\Api\Cms\BaseCmsController;
use App\Http\Requests\Cms\AffiliateRequest;
use App\Models\Entities\Affiliate;
use App\Repositories\Interfaces\AffiliateRepositoryInterface;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;

/**
 * CMS quản lý KOL — Phase 5 AFFILIATE-PLAN.md (trước đó admin phải sửa tay
 * trong DB: `UPDATE affiliate SET status = 1`).
 *
 * KHÔNG có `store`: hồ sơ affiliate chỉ sinh ra khi USER tự đăng ký ở cổng
 * `/account/affiliate` — mỗi affiliate buộc phải gắn với một `user` (UNIQUE
 * user_id). Admin tạo hộ sẽ là hồ sơ không có ai đăng nhập vào được.
 *
 * KHÔNG có `destroy`: xoá KOL kéo theo CASCADE cả click, conversion, payout
 * — tức xoá luôn sổ sách hoa hồng đã trả. Ngừng hợp tác thì dùng Suspended.
 */
class AffiliateController extends BaseCmsController
{
    protected string $permission = 'affiliate';

    public function __construct(
        private readonly AffiliateRepositoryInterface $repo
    ) {
    }

    public function index(Request $request)
    {
        return AffiliateData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
    }

    public function show($id)
    {
        $affiliate = $this->repo->getForCms((int) $id);
        abort_if($affiliate === null, 404);

        return respondSuccess(AffiliateData::fromModel($affiliate));
    }

    public function update(AffiliateRequest $request, Affiliate $affiliate)
    {
        $affiliate = $this->repo->updateFromCms($affiliate, $request->validated());

        return respondSuccess(AffiliateData::fromModel($affiliate), 'affiliate_updated');
    }

    /** Duyệt hồ sơ đang chờ (Pending → Active), đóng dấu approved_at lần đầu. */
    public function approve(Affiliate $affiliate)
    {
        $affiliate = $this->repo->setStatus($affiliate, AffiliateStatus::Active);

        return respondSuccess(AffiliateData::fromModel($affiliate), 'affiliate_approved');
    }

    /**
     * Tạm khoá KOL. Từ lúc này tracking bỏ qua mọi click/coupon của họ
     * (AffiliateStatus::canTrack), nhưng hoa hồng ĐÃ ghi vẫn giữ nguyên —
     * khoá là chặn tương lai, không phải xoá quá khứ.
     */
    public function suspend(Affiliate $affiliate)
    {
        $affiliate = $this->repo->setStatus($affiliate, AffiliateStatus::Suspended);

        return respondSuccess(AffiliateData::fromModel($affiliate), 'affiliate_suspended');
    }

    /**
     * Gán coupon riêng cho KOL — nguồn attribution thứ hai bên cạnh click,
     * dành cho khách gõ mã từ story/TikTok chứ không bấm link.
     */
    public function syncCoupons(Request $request, Affiliate $affiliate)
    {
        $data = $request->validate([
            'coupon_ids'   => 'array',
            'coupon_ids.*' => 'integer|exists:coupon,id',
        ]);

        $affiliate = $this->repo->syncCoupons($affiliate, $data['coupon_ids'] ?? []);

        return respondSuccess(AffiliateData::fromModel($affiliate), 'affiliate_coupons_updated');
    }
}
