<?php

namespace Tests\Feature\Affiliate;

use App\Models\Entities\AffiliateCommissionRule;
use App\Models\Entities\AffiliateConversion;
use App\Models\Entities\Coupon;
use App\Services\Affiliate\AffiliateConversionService;
use Illuminate\Support\Facades\DB;

/**
 * Tiền nong Phase 3 (business ĐÃ CHỐT — AFFILIATE-PLAN.md mục 4):
 * base = sau discount TRƯỚC ship; rate precedence per-item
 * affiliate.commission_rate > rule category (lấy CAO NHẤT) > config global;
 * discount phân bổ tỷ lệ; snapshot + orders.affiliate_id; idempotent.
 */
class AffiliateConversionServiceTest extends AffiliateTestCase
{
    protected function service(): AffiliateConversionService
    {
        return $this->app->make(AffiliateConversionService::class);
    }

    /** Attribution qua cookie click của $affiliate (last-click). */
    protected function attributeViaCookie($affiliate): void
    {
        $click = $this->makeClick($affiliate);
        request()->cookies->set((string) getCoreConfig('affiliate.cookie'), $click->click_token);
    }

    protected function makeOrder(int $id): void
    {
        DB::table('orders')->insert(['id' => $id, 'affiliate_id' => null]);
    }

    public function test_base_sau_discount_truoc_ship_va_rate_global(): void
    {
        $kol = $this->makeAffiliate(); // không rate riêng → global 5%
        $this->attributeViaCookie($kol);
        $this->makeOrder(500);

        $items = [
            ['id' => 11, 'total' => 600000],
            ['id' => 22, 'total' => 400000],
        ];
        $totalData = [
            ['code' => 'sub_total',       'value' => 1000000],
            ['code' => 'coupon:SALE10',   'value' => -100000],
            ['code' => 'shipping',        'value' => 30000],   // KHÔNG được tính
            ['code' => 'total',           'value' => 930000],
        ];

        $this->service()->record(500, $items, $totalData);

        $row = AffiliateConversion::where('order_id', 500)->first();
        $this->assertNotNull($row);
        $this->assertSame(900000, (int) $row->order_total);           // base: 1tr - 100k, bỏ ship
        $this->assertSame(45000, (int) $row->commission);             // (600k+400k)×0.9×5%
        $this->assertSame(5.0, (float) $row->commission_rate);        // rate hiệu dụng
        $this->assertSame((int) $kol->id, (int) $row->affiliate_id);
        $this->assertNotNull($row->click_id);
        $this->assertSame(
            (int) $kol->id,
            (int) DB::table('orders')->where('id', 500)->value('affiliate_id'),
        );
    }

    public function test_rule_category_lay_cao_nhat_thang_global(): void
    {
        $kol = $this->makeAffiliate();
        $this->attributeViaCookie($kol);
        $this->makeOrder(501);

        // SP 11 thuộc 2 category có rule 8% và 3% → lấy 8%; SP 22 không rule → 5%.
        DB::table('product_category')->insert([
            ['product_id' => 11, 'category_id' => 1],
            ['product_id' => 11, 'category_id' => 2],
        ]);
        AffiliateCommissionRule::create(['category_id' => 1, 'rate' => 8]);
        AffiliateCommissionRule::create(['category_id' => 2, 'rate' => 3]);

        $items = [
            ['id' => 11, 'total' => 600000],
            ['id' => 22, 'total' => 400000],
        ];
        $totalData = [['code' => 'sub_total', 'value' => 1000000], ['code' => 'total', 'value' => 1000000]];

        $this->service()->record(501, $items, $totalData);

        $row = AffiliateConversion::where('order_id', 501)->first();
        $this->assertSame(68000, (int) $row->commission);      // 600k×8% + 400k×5%
        $this->assertSame(6.8, (float) $row->commission_rate); // effective
    }

    public function test_rate_rieng_cua_kol_thang_rule_va_global(): void
    {
        $kol = $this->makeAffiliate(['commission_rate' => 10]);
        $this->attributeViaCookie($kol);
        $this->makeOrder(502);

        DB::table('product_category')->insert([['product_id' => 11, 'category_id' => 1]]);
        AffiliateCommissionRule::create(['category_id' => 1, 'rate' => 8]); // phải bị bỏ qua

        $items = [
            ['id' => 11, 'total' => 600000],
            ['id' => 22, 'total' => 400000],
        ];
        $totalData = [['code' => 'sub_total', 'value' => 1000000], ['code' => 'total', 'value' => 1000000]];

        $this->service()->record(502, $items, $totalData);

        $this->assertSame(100000, (int) AffiliateConversion::where('order_id', 502)->first()->commission);
    }

    public function test_reward_voucher_giam_base_freeship_va_ship_thi_khong(): void
    {
        $kol = $this->makeAffiliate();
        $this->attributeViaCookie($kol);
        $this->makeOrder(503);

        $items = [['id' => 11, 'total' => 500000]];
        $totalData = [
            ['code' => 'sub_total',       'value' => 500000],
            ['code' => 'reward',          'value' => -50000],
            ['code' => 'voucher:GC01',    'value' => -100000],
            ['code' => 'coupon_freeship', 'value' => -15000], // giảm phí ship — KHÔNG trừ base
            ['code' => 'shipping',        'value' => 20000],
            ['code' => 'total',           'value' => 355000],
        ];

        $this->service()->record(503, $items, $totalData);

        $row = AffiliateConversion::where('order_id', 503)->first();
        $this->assertSame(350000, (int) $row->order_total);
        $this->assertSame(17500, (int) $row->commission); // 500k×0.7×5%
    }

    public function test_attribution_coupon_ghi_coupon_code_khong_click_id(): void
    {
        $kol = $this->makeAffiliate();
        $coupon = Coupon::create(['code' => 'KOLX', 'type' => 0]);
        DB::table('affiliate_coupon')->insert(['affiliate_id' => $kol->id, 'coupon_id' => $coupon->id]);
        session()->put(getCoreConfig('session.applied_coupons'), ['KOLX']);
        $this->makeOrder(504);

        $this->service()->record(
            504,
            [['id' => 11, 'total' => 200000]],
            [['code' => 'sub_total', 'value' => 200000], ['code' => 'total', 'value' => 200000]],
        );

        $row = AffiliateConversion::where('order_id', 504)->first();
        $this->assertSame('KOLX', $row->coupon_code);
        $this->assertNull($row->click_id);
    }

    public function test_khong_attribution_thi_khong_ghi_gi(): void
    {
        $this->makeOrder(505);

        $this->service()->record(
            505,
            [['id' => 11, 'total' => 200000]],
            [['code' => 'sub_total', 'value' => 200000]],
        );

        $this->assertSame(0, AffiliateConversion::count());
        $this->assertNull(DB::table('orders')->where('id', 505)->value('affiliate_id'));
    }

    public function test_goi_lap_khong_tao_dup(): void
    {
        $kol = $this->makeAffiliate();
        $this->attributeViaCookie($kol);
        $this->makeOrder(506);

        $items = [['id' => 11, 'total' => 200000]];
        $totalData = [['code' => 'sub_total', 'value' => 200000]];

        $this->service()->record(506, $items, $totalData);
        $this->service()->record(506, $items, $totalData);

        $this->assertSame(1, AffiliateConversion::where('order_id', 506)->count());
    }
}
