<?php

namespace Tests\Feature\Affiliate;

use App\Enums\AffiliateStatus;
use App\Models\Entities\Coupon;
use App\Models\Entities\User;
use App\Services\Affiliate\AffiliateAttributionService;
use Illuminate\Support\Facades\DB;

/**
 * Attribution tại checkout (Phase 2/6): precedence coupon KOL > cookie
 * last-click, self-referral block, token hết hạn, hệ tắt.
 */
class AffiliateAttributionTest extends AffiliateTestCase
{
    protected function service(): AffiliateAttributionService
    {
        return $this->app->make(AffiliateAttributionService::class);
    }

    protected function grantCoupon(int $affiliateId, string $code): void
    {
        $coupon = Coupon::create(['code' => $code, 'type' => 0]);
        DB::table('affiliate_coupon')->insert([
            'affiliate_id' => $affiliateId,
            'coupon_id'    => $coupon->id,
        ]);
    }

    public function test_coupon_kol_uu_tien_hon_cookie(): void
    {
        $kolCoupon = $this->makeAffiliate(['user_id' => 1]);
        $kolClick = $this->makeAffiliate(['user_id' => 2]);
        $this->grantCoupon((int) $kolCoupon->id, 'KOLA10');
        $click = $this->makeClick($kolClick);

        session()->put(getCoreConfig('session.applied_coupons'), ['KOLA10']);
        request()->cookies->set((string) getCoreConfig('affiliate.cookie'), $click->click_token);

        $attr = $this->service()->resolve();

        $this->assertNotNull($attr);
        $this->assertSame((int) $kolCoupon->id, $attr->affiliateId);
        $this->assertSame('KOLA10', $attr->couponCode);
        $this->assertNull($attr->clickId);
    }

    public function test_cookie_fallback_khi_khong_co_coupon(): void
    {
        $kol = $this->makeAffiliate(['user_id' => 2, 'commission_rate' => 8.5]);
        $click = $this->makeClick($kol);

        request()->cookies->set((string) getCoreConfig('affiliate.cookie'), $click->click_token);

        $attr = $this->service()->resolve();

        $this->assertNotNull($attr);
        $this->assertSame((int) $kol->id, $attr->affiliateId);
        $this->assertSame((int) $click->id, $attr->clickId);
        $this->assertNull($attr->couponCode);
        $this->assertSame(8.5, $attr->commissionRate);
    }

    public function test_token_qua_han_cookie_window_bi_tu_choi(): void
    {
        $kol = $this->makeAffiliate();
        $click = $this->makeClick($kol, ['created_at' => now()->subDays(31)]);

        request()->cookies->set((string) getCoreConfig('affiliate.cookie'), $click->click_token);

        $this->assertNull($this->service()->resolve());
    }

    public function test_affiliate_suspended_khong_duoc_attribution(): void
    {
        $kol = $this->makeAffiliate(['status' => AffiliateStatus::Suspended->value]);
        $click = $this->makeClick($kol);

        request()->cookies->set((string) getCoreConfig('affiliate.cookie'), $click->click_token);

        $this->assertNull($this->service()->resolve());
    }

    public function test_self_referral_bi_chan_ca_coupon_lan_cookie(): void
    {
        $kol = $this->makeAffiliate(['user_id' => 7]);
        $this->grantCoupon((int) $kol->id, 'KOLSELF');
        $click = $this->makeClick($kol);

        session()->put(getCoreConfig('session.applied_coupons'), ['KOLSELF']);
        request()->cookies->set((string) getCoreConfig('affiliate.cookie'), $click->click_token);
        $this->forceWebContext();

        // Control: user KHÁC chủ affiliate → vẫn attribution bình thường.
        $other = new User();
        $other->id = 8;
        auth()->setUser($other);
        $this->assertNotNull($this->service()->resolve());

        // Chính chủ mua qua link/mã của mình → chặn.
        $self = new User();
        $self->id = 7;
        auth()->setUser($self);
        $this->assertNull($this->service()->resolve());
    }

    public function test_he_tat_thi_khong_attribution(): void
    {
        $kol = $this->makeAffiliate();
        $click = $this->makeClick($kol);
        request()->cookies->set((string) getCoreConfig('affiliate.cookie'), $click->click_token);

        $this->configDb['config_affiliate_enabled'] = 0;

        $this->assertNull($this->service()->resolve());
    }
}
