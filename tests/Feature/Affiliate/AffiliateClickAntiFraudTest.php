<?php

namespace Tests\Feature\Affiliate;

use App\Data\Affiliate\AffiliateClickData;
use App\Models\Entities\Affiliate;
use App\Models\Entities\AffiliateClick;
use App\Repositories\Interfaces\AffiliateClickRepositoryInterface;

/**
 * Anti-fraud click (Phase 6): dedupe IP+UA (không phụ thuộc session/cookie),
 * cap click/ngày/affiliate, throttle per-link.
 */
class AffiliateClickAntiFraudTest extends AffiliateTestCase
{
    protected function repo(): AffiliateClickRepositoryInterface
    {
        return $this->app->make(AffiliateClickRepositoryInterface::class);
    }

    protected function clickData(Affiliate $affiliate, array $attrs = []): AffiliateClickData
    {
        return new AffiliateClickData(
            affiliateId: (int) $affiliate->id,
            affiliateLinkId: $attrs['linkId'] ?? null,
            sessionId: $attrs['session'] ?? 'sess-1',
            ip: $attrs['ip'] ?? '203.0.113.10',
            userAgent: $attrs['ua'] ?? 'Mozilla/5.0 Test',
        );
    }

    public function test_dedupe_ip_ua_khong_phu_thuoc_session(): void
    {
        $kol = $this->makeAffiliate();

        $first = $this->repo()->recordClick($this->clickData($kol, ['session' => 'sess-1']));
        // Bot xóa cookie/session rồi click lại — IP+UA giống → tái dùng click cũ.
        $second = $this->repo()->recordClick($this->clickData($kol, ['session' => 'sess-2']));

        $this->assertNotNull($first);
        $this->assertSame((int) $first->id, (int) $second->id);
        $this->assertSame(1, AffiliateClick::count());
        $this->assertSame(1, (int) Affiliate::find($kol->id)->clicks_count);
    }

    public function test_ip_khac_van_log_binh_thuong(): void
    {
        $kol = $this->makeAffiliate();

        $this->repo()->recordClick($this->clickData($kol, ['ip' => '203.0.113.10']));
        $this->repo()->recordClick($this->clickData($kol, ['ip' => '203.0.113.11']));

        $this->assertSame(2, AffiliateClick::count());
        $this->assertSame(2, (int) Affiliate::find($kol->id)->clicks_count);
    }

    public function test_dedupe_scope_theo_link(): void
    {
        $kol = $this->makeAffiliate();

        // Cùng IP+UA nhưng 2 link khác nhau của cùng KOL → 2 click riêng
        // (per-link stats không lệch — fix review 2026-07-11).
        $a = $this->repo()->recordClick($this->clickData($kol, ['linkId' => 1]));
        $b = $this->repo()->recordClick($this->clickData($kol, ['linkId' => 2]));

        $this->assertNotSame((int) $a->id, (int) $b->id);
        $this->assertSame(2, AffiliateClick::count());
    }

    public function test_cap_click_moi_ngay(): void
    {
        config(['core.config.affiliate.max_clicks_per_day' => 2]);
        $kol = $this->makeAffiliate();

        $this->assertNotNull($this->repo()->recordClick($this->clickData($kol, ['ip' => '10.0.0.1'])));
        $this->assertNotNull($this->repo()->recordClick($this->clickData($kol, ['ip' => '10.0.0.2'])));
        // Click thứ 3 trong ngày vượt cap → null (bỏ log, redirect vẫn chạy).
        $this->assertNull($this->repo()->recordClick($this->clickData($kol, ['ip' => '10.0.0.3'])));

        $this->assertSame(2, AffiliateClick::count());
        $this->assertSame(2, (int) Affiliate::find($kol->id)->clicks_count);
    }

    public function test_cap_bang_khong_nghia_la_khong_gioi_han(): void
    {
        config(['core.config.affiliate.max_clicks_per_day' => 0]);
        $kol = $this->makeAffiliate();

        for ($i = 1; $i <= 5; $i++) {
            $this->assertNotNull(
                $this->repo()->recordClick($this->clickData($kol, ['ip' => "10.0.1.{$i}"])),
            );
        }

        $this->assertSame(5, AffiliateClick::count());
    }

    public function test_find_recent_throttle_theo_session_va_link(): void
    {
        $kol = $this->makeAffiliate();
        $click = $this->repo()->recordClick($this->clickData($kol, ['linkId' => 1, 'session' => 'sess-x']));

        // Cùng session + link trong window → tái dùng.
        $found = $this->repo()->findRecent((int) $kol->id, 'sess-x', 30, 1);
        $this->assertSame((int) $click->id, (int) $found->id);

        // Link khác → không match (log riêng cho per-link stats).
        $this->assertNull($this->repo()->findRecent((int) $kol->id, 'sess-x', 30, 2));

        // Click ?ref= trực tiếp (link null) → không match click của link 1.
        $this->assertNull($this->repo()->findRecent((int) $kol->id, 'sess-x', 30, null));
    }
}
