<?php

namespace Tests\Feature\Affiliate;

use App\Enums\AffiliateStatus;
use App\Models\Entities\AffiliateConversion;
use App\Services\Affiliate\AffiliatePortalService;

/**
 * Cổng affiliate Phase 4 + cap link Phase 6: đăng ký (pending/auto-approve,
 * idempotent), tạo link (validate + cap max_links), chartSeries đủ 30 ngày.
 */
class AffiliatePortalTest extends AffiliateTestCase
{
    protected function service(): AffiliatePortalService
    {
        return $this->app->make(AffiliatePortalService::class);
    }

    public function test_dang_ky_mac_dinh_pending_khong_approved_at(): void
    {
        $affiliate = $this->service()->register(10, ['bank_name' => 'VCB']);

        $this->assertSame(AffiliateStatus::Pending->value, (int) $affiliate->status);
        $this->assertNull($affiliate->approved_at);
        $this->assertSame(8, strlen((string) $affiliate->code));
        $this->assertSame('VCB', $affiliate->payment_info['bank_name'] ?? null);

        // Round-trip DB: chống tái phát bug double json-encode do
        // Base::save() refill (xem Affiliate::paymentInfo()).
        $fresh = $affiliate->fresh();
        $this->assertIsArray($fresh->payment_info);
        $this->assertSame('VCB', $fresh->payment_info['bank_name']);
    }

    public function test_dang_ky_auto_approve_theo_config(): void
    {
        $this->configDb['config_affiliate_auto_approve'] = 1;

        $affiliate = $this->service()->register(11, []);

        $this->assertSame(AffiliateStatus::Active->value, (int) $affiliate->status);
        $this->assertNotNull($affiliate->approved_at);
    }

    public function test_dang_ky_lai_tra_ve_ho_so_cu(): void
    {
        $first = $this->service()->register(12, []);
        $second = $this->service()->register(12, ['bank_name' => 'khac']);

        $this->assertSame((int) $first->id, (int) $second->id);
        $this->assertSame(1, \App\Models\Entities\Affiliate::count());
    }

    public function test_tao_link_hop_le_va_detect_product_id(): void
    {
        config(['app.url' => 'https://infun.vn']);
        $kol = $this->makeAffiliate();

        [$link, $error] = $this->service()->createLink($kol, 'https://infun.vn/gau-bong-p123', 'tiktok');

        $this->assertNull($error);
        $this->assertSame('/gau-bong-p123', $link->destination_url);
        $this->assertSame(123, (int) $link->product_id);
        $this->assertSame('tiktok', $link->sub_id);
        $this->assertSame(8, strlen((string) $link->slug));
    }

    public function test_tao_link_domain_la_bi_tu_choi(): void
    {
        config(['app.url' => 'https://infun.vn']);
        $kol = $this->makeAffiliate();

        [$link, $error] = $this->service()->createLink($kol, 'https://evil.com/x');

        $this->assertNull($link);
        $this->assertNotNull($error);
    }

    public function test_cap_max_links(): void
    {
        config(['app.url' => 'https://infun.vn', 'core.config.affiliate.max_links' => 2]);
        $kol = $this->makeAffiliate();

        [$a] = $this->service()->createLink($kol, '/san-pham');
        [$b] = $this->service()->createLink($kol, '/khuyen-mai');
        [$c, $error] = $this->service()->createLink($kol, '/bai-viet');

        $this->assertNotNull($a);
        $this->assertNotNull($b);
        $this->assertNull($c);
        $this->assertNotNull($error);
    }

    public function test_chart_series_du_30_ngay_ngay_trong_bang_khong(): void
    {
        $kol = $this->makeAffiliate();
        $this->makeClick($kol, ['created_at' => now()]);
        $this->makeClick($kol, ['created_at' => now(), 'ip' => '10.9.9.9']);
        $this->makeClick($kol, ['created_at' => now()->subDays(3)]);
        AffiliateConversion::create([
            'affiliate_id' => $kol->id,
            'order_id'     => 900,
            'order_total'  => 100000,
            'commission'   => 5000,
            'status'       => 0,
        ]);

        $stats = $this->service()->dashboard($kol->fresh());
        $chart = $stats['chart'];

        $this->assertCount(30, $chart['labels']);
        $this->assertCount(30, $chart['clicks']);
        $this->assertCount(30, $chart['conversions']);
        $this->assertSame(2, $chart['clicks'][29]);        // hôm nay: 2 click
        $this->assertSame(1, $chart['clicks'][26]);        // 3 ngày trước: 1
        $this->assertSame(0, $chart['clicks'][25]);        // ngày trống = 0
        $this->assertSame(1, $chart['conversions'][29]);
        $this->assertSame(3, array_sum($chart['clicks']));
        $this->assertSame(1, array_sum($chart['conversions']));

        // Stat cards đọc từ getStatusTotals + aggregate.
        $this->assertSame(1, $stats['pending_count']);
        $this->assertSame(5000, $stats['pending_commission']);
    }
}
