<?php

namespace Tests\Feature\Affiliate;

use App\Data\Affiliate\AffiliateConversionData;
use App\Enums\AffiliateConversionStatus;
use App\Models\Entities\AffiliateConversion;
use App\Repositories\Interfaces\AffiliateConversionRepositoryInterface;

/**
 * Vòng đời conversion (Phase 3/6): idempotency theo order_id,
 * Pending → Approved (giao thành công) / Rejected (hủy), Paid bất khả xâm.
 */
class AffiliateConversionLifecycleTest extends AffiliateTestCase
{
    protected function repo(): AffiliateConversionRepositoryInterface
    {
        return $this->app->make(AffiliateConversionRepositoryInterface::class);
    }

    protected function data(int $orderId = 100, int $commission = 45000): AffiliateConversionData
    {
        return new AffiliateConversionData(
            affiliateId: (int) $this->makeAffiliate()->id,
            orderId: $orderId,
            orderTotal: 900000,
            commission: $commission,
            rate: 5.0,
            clickId: null,
            couponCode: null,
        );
    }

    public function test_record_idempotent_theo_order_id(): void
    {
        $data = $this->data(orderId: 100);

        $this->repo()->recordConversion($data);
        $this->repo()->recordConversion($data); // gọi lặp (retry/double-submit)

        $this->assertSame(1, AffiliateConversion::where('order_id', 100)->count());
        $row = AffiliateConversion::where('order_id', 100)->first();
        $this->assertSame(AffiliateConversionStatus::Pending->value, (int) $row->status);
        $this->assertSame(45000, (int) $row->commission);
    }

    public function test_commission_khong_duong_thi_khong_ghi(): void
    {
        $this->repo()->recordConversion($this->data(orderId: 101, commission: 0));

        $this->assertSame(0, AffiliateConversion::where('order_id', 101)->count());
    }

    public function test_don_giao_thanh_cong_pending_sang_approved(): void
    {
        $this->repo()->recordConversion($this->data(orderId: 102));

        $this->repo()->approveForOrder(102);

        $row = AffiliateConversion::where('order_id', 102)->first();
        $this->assertSame(AffiliateConversionStatus::Approved->value, (int) $row->status);
        $this->assertNotNull($row->approved_at);

        // Observer chạy lặp (status đổi nhiều lần) — vô hại.
        $firstApprovedAt = $row->approved_at;
        $this->repo()->approveForOrder(102);
        $this->assertEquals($firstApprovedAt, AffiliateConversion::where('order_id', 102)->first()->approved_at);
    }

    public function test_don_huy_pending_va_approved_sang_rejected(): void
    {
        $this->repo()->recordConversion($this->data(orderId: 103));
        $this->repo()->rejectForOrder(103);
        $this->assertSame(
            AffiliateConversionStatus::Rejected->value,
            (int) AffiliateConversion::where('order_id', 103)->first()->status,
        );

        $this->repo()->recordConversion($this->data(orderId: 104));
        $this->repo()->approveForOrder(104);
        $this->repo()->rejectForOrder(104); // hoàn/hủy sau khi đã duyệt
        $this->assertSame(
            AffiliateConversionStatus::Rejected->value,
            (int) AffiliateConversion::where('order_id', 104)->first()->status,
        );
    }

    public function test_da_paid_thi_khong_bi_reject_hay_approve_de(): void
    {
        $this->repo()->recordConversion($this->data(orderId: 105));
        AffiliateConversion::where('order_id', 105)
            ->update(['status' => AffiliateConversionStatus::Paid->value]);

        $this->repo()->rejectForOrder(105);
        $this->repo()->approveForOrder(105);

        $this->assertSame(
            AffiliateConversionStatus::Paid->value,
            (int) AffiliateConversion::where('order_id', 105)->first()->status,
        );
    }

    public function test_reject_roi_khong_approve_lai_duoc(): void
    {
        // Đơn hủy xong đổi status sang giao thành công (case hi hữu sửa tay):
        // approve chỉ nhận từ Pending nên Rejected giữ nguyên — an toàn tiền.
        $this->repo()->recordConversion($this->data(orderId: 106));
        $this->repo()->rejectForOrder(106);
        $this->repo()->approveForOrder(106);

        $this->assertSame(
            AffiliateConversionStatus::Rejected->value,
            (int) AffiliateConversion::where('order_id', 106)->first()->status,
        );
    }
}
