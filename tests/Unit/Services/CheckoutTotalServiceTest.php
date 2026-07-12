<?php

namespace Tests\Unit\Services;

use App\Models\Entities\Coupon;
use App\Models\Entities\User;
use App\Repositories\Interfaces\UserRewardRepositoryInterface;
use App\Services\Checkout\CheckoutPromotions;
use App\Services\Checkout\CheckoutTotalService;
use App\Services\Checkout\PromotionService;
use App\Services\Checkout\ShippingFeeService;
use App\Services\ConfigDbService;
use App\Services\Currency\CurrencyService;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Tier 1 — toán tiền checkout: CheckoutTotalService.
 *
 * Chốt các phép tính "sai là mất tiền":
 *  - coupon discount bị kẹp trong [0, total] (không âm tổng, không vượt tổng);
 *  - freeship coupon KHÔNG tạo dòng giảm giá hàng;
 *  - reward redemption: rate + cap % + intdiv (điểm lẻ không được tính);
 *  - freeship cap helper.
 *
 * money() được stub qua CurrencyService fake -> không chạm DB currency.
 * getConfigDb qua ConfigDbService fake (data mutable theo từng test).
 */
class CheckoutTotalServiceTest extends TestCase
{
    protected $fakeConfig;

    protected function setUp(): void
    {
        parent::setUp();

        // getConfigDb(...) -> đọc từ mảng data fake (mặc định rỗng).
        $this->fakeConfig = new class extends ConfigDbService
        {
            public array $data = [];

            public function __construct()
            {
            }

            public function getConfigs(): array
            {
                return $this->data;
            }
        };
        $this->app->instance(ConfigDbService::class, $this->fakeConfig);

        // money() -> CurrencyService::formatPrice: chỉ cần chuỗi, không cần DB.
        $currency = Mockery::mock(CurrencyService::class);
        $currency->shouldReceive('formatPrice')->andReturnUsing(
            fn ($price) => number_format((float) $price),
        );
        $this->app->instance(CurrencyService::class, $currency);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function makeService(?UserRewardRepositoryInterface $rewardRepo = null): CheckoutTotalService
    {
        $promo = Mockery::mock(PromotionService::class);
        $promo->shouldReceive('resolveVouchers')->andReturn(['applied' => []]);

        return new CheckoutTotalService(
            Mockery::mock(ShippingFeeService::class),
            $rewardRepo ?? Mockery::mock(UserRewardRepositoryInterface::class),
            $promo,
        );
    }

    protected function lineValue(array $totalData, string $code): ?int
    {
        foreach ($totalData as $row) {
            if (($row['code'] ?? null) === $code) {
                return (int) $row['value'];
            }
        }

        return null;
    }

    protected function linePoints(array $totalData, string $code): ?int
    {
        foreach ($totalData as $row) {
            if (($row['code'] ?? null) === $code) {
                return (int) ($row['points'] ?? 0);
            }
        }

        return null;
    }

    public function test_coupon_giam_gia_binh_thuong(): void
    {
        $promotions = (new CheckoutPromotions())
            ->setItems([['total' => 100000]])
            ->setAppliedCoupons([
                ['coupon' => new Coupon(['code' => 'SALE', 'type' => 1]), 'discount' => 30000, 'type' => 1],
            ], false);

        [$totalData, $total] = $this->makeService()->build($promotions, withShipping: false);

        $this->assertSame(70000, $total);
        $this->assertSame(-30000, $this->lineValue($totalData, 'coupon:SALE'));
    }

    public function test_coupon_bi_kep_khong_vuot_qua_tong(): void
    {
        $promotions = (new CheckoutPromotions())
            ->setItems([['total' => 50000]])
            ->setAppliedCoupons([
                ['coupon' => new Coupon(['code' => 'BIG', 'type' => 1]), 'discount' => 999999, 'type' => 1],
            ], false);

        [$totalData, $total] = $this->makeService()->build($promotions, withShipping: false);

        $this->assertSame(0, $total);                                    // không âm
        $this->assertSame(-50000, $this->lineValue($totalData, 'coupon:BIG')); // kẹp đúng bằng tổng
    }

    public function test_coupon_freeship_khong_tao_dong_giam_hang(): void
    {
        $promotions = (new CheckoutPromotions())
            ->setItems([['total' => 100000]])
            ->setAppliedCoupons([
                ['coupon' => new Coupon(['code' => 'FREESHIP', 'type' => 3]), 'discount' => 30000, 'type' => 3],
            ], true);

        [$totalData, $total] = $this->makeService()->build($promotions, withShipping: false);

        $this->assertSame(100000, $total);
        $this->assertNull($this->lineValue($totalData, 'coupon:FREESHIP'));
    }

    public function test_reward_quy_doi_theo_rate(): void
    {
        $this->fakeConfig->data = [
            'config_reward_point_enabled'      => 1,
            'config_reward_redeem_rate'        => 100,
            'config_reward_redeem_max_percent' => 100,
        ];
        session()->put('reward', 3);
        $this->actingAs((new User())->forceFill(['id' => 1]));

        $rewardRepo = Mockery::mock(UserRewardRepositoryInterface::class);
        $rewardRepo->shouldReceive('getTotalPoints')->andReturn(10);

        $promotions = (new CheckoutPromotions())
            ->setItems([['total' => 100000]])
            ->setAppliedCoupons([], false);

        [$totalData, $total] = $this->makeService($rewardRepo)->build($promotions, withShipping: false);

        $this->assertSame(99700, $total);                          // 100000 - 3*100
        $this->assertSame(-300, $this->lineValue($totalData, 'reward'));
        $this->assertSame(3, $this->linePoints($totalData, 'reward'));
    }

    public function test_reward_bi_gioi_han_theo_cap_phan_tram(): void
    {
        $this->fakeConfig->data = [
            'config_reward_point_enabled'      => 1,
            'config_reward_redeem_rate'        => 100,
            'config_reward_redeem_max_percent' => 10, // trần 10% đơn
        ];
        session()->put('reward', 1000);
        $this->actingAs((new User())->forceFill(['id' => 1]));

        $rewardRepo = Mockery::mock(UserRewardRepositoryInterface::class);
        $rewardRepo->shouldReceive('getTotalPoints')->andReturn(1000);

        $promotions = (new CheckoutPromotions())
            ->setItems([['total' => 100000]])
            ->setAppliedCoupons([], false);

        [$totalData, $total] = $this->makeService($rewardRepo)->build($promotions, withShipping: false);

        $this->assertSame(90000, $total);                            // trần giảm = 10% = 10000
        $this->assertSame(-10000, $this->lineValue($totalData, 'reward'));
        $this->assertSame(100, $this->linePoints($totalData, 'reward')); // 10000 / rate 100
    }

    public function test_freeship_cap_lay_discount_max_roi_toi_discount(): void
    {
        $svc = $this->makeService();
        $m = new ReflectionMethod($svc, 'freeshipCap');
        $m->setAccessible(true);

        $this->assertSame(20000, $m->invoke($svc, new Coupon(['discount_max' => 20000, 'discount' => 0])));
        $this->assertSame(15000, $m->invoke($svc, new Coupon(['discount_max' => 0, 'discount' => 15000])));
    }

    public function test_freeship_ship_discount_kep_theo_cap(): void
    {
        $svc = $this->makeService();
        $m = new ReflectionMethod($svc, 'freeshipShipDiscount');
        $m->setAccessible(true);

        $entry = ['coupon' => new Coupon(['discount_max' => 20000, 'discount' => 0])];

        $this->assertSame(20000, $m->invoke($svc, $entry, 30000));   // cap < phí ship
        $this->assertSame(30000, $m->invoke($svc, ['coupon' => new Coupon(['discount_max' => 0, 'discount' => 0])], 30000)); // cap 0 -> free toàn bộ
        $this->assertSame(0, $m->invoke($svc, null, 30000));         // không có entry
        $this->assertSame(0, $m->invoke($svc, $entry, 0));           // không có phí ship
    }
}
