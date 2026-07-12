<?php

namespace Tests\Unit\Services;

use App\Models\Entities\Coupon;
use App\Repositories\Interfaces\CouponRepositoryInterface;
use App\Services\Cart\CartCouponContext;
use App\Services\Cart\CouponService;
use App\Services\ConfigDbService;
use Mockery;
use Tests\TestCase;

/**
 * Tier 1 — luật quyết định coupon: CouponService::validateForCart.
 *
 * Mỗi nhánh reject phải trả về 1 lý do (string khác null); pass hết trả null.
 * Không đụng DB: Coupon dựng in-memory (setRelation cho couponProducts/
 * couponCategories), repo mock, getConfigDb('config_currency') fake.
 */
class CouponServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // getConfigDb('config_currency') dùng ở message need_more -> fake khỏi chạm DB.
        $fakeConfig = new class extends ConfigDbService
        {
            public function __construct()
            {
            }

            public function getConfigs(): array
            {
                return ['config_currency' => 'đ'];
            }
        };
        $this->app->instance(ConfigDbService::class, $fakeConfig);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function service(): CouponService
    {
        // validateForCart không gọi repo vì test luôn truyền usedByUser tường minh.
        return new CouponService(Mockery::mock(CouponRepositoryInterface::class));
    }

    protected function makeCoupon(array $attrs = [], array $relations = []): Coupon
    {
        $coupon = new Coupon(array_merge([
            'code'        => 'TEST',
            'type'        => 1, // percent (freeship = 3)
            'apply_scope' => 0, // all
            'logged'      => 0,
            'used_count'  => 0,
        ], $attrs));

        foreach ($relations as $name => $items) {
            $coupon->setRelation($name, collect($items));
        }

        return $coupon;
    }

    protected function context(array $o = []): CartCouponContext
    {
        return new CartCouponContext(
            subtotal:    $o['subtotal']    ?? 500000,
            productIds:  $o['productIds']  ?? [10, 20],
            categoryIds: $o['categoryIds'] ?? [5, 6],
            userId:      array_key_exists('userId', $o) ? $o['userId'] : 1,
            hasShipping: $o['hasShipping'] ?? true,
        );
    }

    public function test_pass_het_dieu_kien_tra_null(): void
    {
        $reason = $this->service()->validateForCart(
            $this->makeCoupon(),
            $this->context(),
            usedByUser: 0,
        );

        $this->assertNull($reason);
    }

    public function test_freeship_khi_gio_chua_co_shipping_bi_tu_choi(): void
    {
        $reason = $this->service()->validateForCart(
            $this->makeCoupon(['type' => 3]), // freeship
            $this->context(['hasShipping' => false]),
            usedByUser: 0,
        );

        $this->assertNotNull($reason);
    }

    public function test_duoi_min_subtotal_bi_tu_choi(): void
    {
        $reason = $this->service()->validateForCart(
            $this->makeCoupon(['min_subtotal' => 1000000]),
            $this->context(['subtotal' => 500000]),
            usedByUser: 0,
        );

        $this->assertNotNull($reason);
    }

    public function test_dat_min_subtotal_thi_pass(): void
    {
        $reason = $this->service()->validateForCart(
            $this->makeCoupon(['min_subtotal' => 500000]),
            $this->context(['subtotal' => 500000]),
            usedByUser: 0,
        );

        $this->assertNull($reason);
    }

    public function test_coupon_logged_ma_khach_vang_lai_bi_tu_choi(): void
    {
        $reason = $this->service()->validateForCart(
            $this->makeCoupon(['logged' => 1]),
            $this->context(['userId' => null]),
            usedByUser: 0,
        );

        $this->assertNotNull($reason);
    }

    public function test_het_luot_dung_tong_bi_tu_choi(): void
    {
        $reason = $this->service()->validateForCart(
            $this->makeCoupon(['uses_total' => 5, 'used_count' => 5]),
            $this->context(),
            usedByUser: 0,
        );

        $this->assertNotNull($reason);
    }

    public function test_khach_dung_qua_han_muc_ca_nhan_bi_tu_choi(): void
    {
        $reason = $this->service()->validateForCart(
            $this->makeCoupon(['uses_customer' => 2]),
            $this->context(['userId' => 1]),
            usedByUser: 2, // đã dùng đủ 2 lần
        );

        $this->assertNotNull($reason);
    }

    public function test_con_han_muc_ca_nhan_thi_pass(): void
    {
        $reason = $this->service()->validateForCart(
            $this->makeCoupon(['uses_customer' => 2]),
            $this->context(['userId' => 1]),
            usedByUser: 1, // mới dùng 1/2
        );

        $this->assertNull($reason);
    }

    public function test_scope_products_khong_giao_bi_tu_choi(): void
    {
        $reason = $this->service()->validateForCart(
            $this->makeCoupon(
                ['apply_scope' => 1], // products
                ['couponProducts' => [['product_id' => 999]]],
            ),
            $this->context(['productIds' => [10, 20]]),
            usedByUser: 0,
        );

        $this->assertNotNull($reason);
    }

    public function test_scope_products_co_giao_thi_pass(): void
    {
        $reason = $this->service()->validateForCart(
            $this->makeCoupon(
                ['apply_scope' => 1],
                ['couponProducts' => [['product_id' => 10]]],
            ),
            $this->context(['productIds' => [10, 20]]),
            usedByUser: 0,
        );

        $this->assertNull($reason);
    }

    public function test_scope_categories_khong_giao_bi_tu_choi(): void
    {
        $reason = $this->service()->validateForCart(
            $this->makeCoupon(
                ['apply_scope' => 2], // categories
                ['couponCategories' => [['category_id' => 999]]],
            ),
            $this->context(['categoryIds' => [5, 6]]),
            usedByUser: 0,
        );

        $this->assertNotNull($reason);
    }

    public function test_scope_categories_co_giao_thi_pass(): void
    {
        $reason = $this->service()->validateForCart(
            $this->makeCoupon(
                ['apply_scope' => 2],
                ['couponCategories' => [['category_id' => 5]]],
            ),
            $this->context(['categoryIds' => [5, 6]]),
            usedByUser: 0,
        );

        $this->assertNull($reason);
    }
}
