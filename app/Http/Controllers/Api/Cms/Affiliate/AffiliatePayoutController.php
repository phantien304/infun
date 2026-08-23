<?php

namespace App\Http\Controllers\Api\Cms\Affiliate;

use App\Data\Cms\AffiliatePayoutData;
use App\Http\Controllers\Api\Cms\BaseCmsController;
use App\Models\Entities\AffiliatePayout;
use App\Repositories\Interfaces\AffiliateConversionRepositoryInterface;
use App\Repositories\Interfaces\AffiliatePayoutRepositoryInterface;
use App\Services\Affiliate\AffiliatePayoutService;
use Illuminate\Http\Request;
use Spatie\LaravelData\PaginatedDataCollection;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * CMS chi trả hoa hồng — Phase 5 AFFILIATE-PLAN.md.
 *
 * Ba thao tác thật sự: xem trước kỳ, chốt kỳ, đánh dấu đã chuyển khoản
 * (+ huỷ kỳ chốt nhầm). Toàn bộ nghiệp vụ nằm ở AffiliatePayoutService —
 * controller chỉ dịch HTTP.
 */
class AffiliatePayoutController extends BaseCmsController
{
    protected string $permission = 'affiliate-payout';

    public function __construct(
        private readonly AffiliatePayoutRepositoryInterface $repo,
        private readonly AffiliateConversionRepositoryInterface $conversionRepo,
        private readonly AffiliatePayoutService $service,
    ) {
    }

    public function index(Request $request)
    {
        return AffiliatePayoutData::collect($this->repo->listForCms($request), PaginatedDataCollection::class);
    }

    public function show($id)
    {
        $payout = $this->repo->getForCms((int) $id);
        abort_if($payout === null, 404);

        // Nạp qua repository thay vì $payout->affiliateConversions()->get():
        // giữ mọi truy vấn conversion ở một chỗ, và listForPayout() đã eager
        // load `order` sẵn cho bảng chi tiết.
        $payout->setRelation('affiliateConversions', $this->conversionRepo->listForPayout((int) $payout->id));

        return respondSuccess(AffiliatePayoutData::fromModel($payout));
    }

    /**
     * Xem trước: ai đủ điều kiện, ai chưa đạt ngưỡng tối thiểu. KHÔNG ghi gì.
     * Bấm chốt kỳ rồi mới xem là quá muộn — kế toán đã cầm danh sách đi
     * chuyển khoản.
     */
    public function preview(Request $request)
    {
        return respondSuccess($this->service->preview($request->input('period')));
    }

    /** Chốt kỳ: gom conversion đủ hold_days + đạt min_payout thành các payout. */
    public function closePeriod(Request $request)
    {
        $request->validate(['period' => 'nullable|string|regex:/^\d{4}-\d{2}$/']);

        return respondSuccess($this->service->closePeriod($request->input('period')), 'affiliate_payout_closed');
    }

    /** Kế toán đã chuyển khoản xong. */
    public function markPaid(Request $request, AffiliatePayout $affiliatePayout)
    {
        $data = $request->validate(['note' => 'nullable|string|max:1000']);

        $payout = $this->repo->markPaid($affiliatePayout, $data['note'] ?? null);

        return respondSuccess(AffiliatePayoutData::fromModel($payout), 'affiliate_payout_paid');
    }

    /** Huỷ kỳ chốt nhầm — conversion quay lại Approved để vào kỳ sau. */
    public function cancel(Request $request, AffiliatePayout $affiliatePayout)
    {
        $data = $request->validate(['note' => 'nullable|string|max:1000']);

        [$payout, $error] = $this->service->cancelPayout($affiliatePayout, $data['note'] ?? null);
        if ($error !== null) {
            return respondError($error, 422);
        }

        return respondSuccess(AffiliatePayoutData::fromModel($payout), 'affiliate_payout_cancelled');
    }

    /**
     * File CSV chuyển khoản của một kỳ.
     *
     * Dùng StreamedResponse + BOM UTF-8: Excel bản Windows mặc định đọc CSV
     * theo bảng mã hệ thống, không có BOM thì tên người nhận tiếng Việt ra
     * ký tự rác — và đây là file đưa cho kế toán gõ lệnh chuyển tiền.
     */
    public function export(Request $request): StreamedResponse
    {
        $request->validate(['period' => 'required|string|regex:/^\d{4}-\d{2}$/']);
        $period = (string) $request->input('period');

        [$header, $rows] = $this->service->exportRows($period);

        return response()->streamDownload(function () use ($header, $rows) {
            $out = fopen('php://output', 'wb');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $header);
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
        }, "affiliate-payout-{$period}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
