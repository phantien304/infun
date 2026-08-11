<?php

namespace Tests\Unit\Services;

use App\Models\Entities\Coupon;
use App\Models\Entities\User;
use App\Models\Entities\Voucher;
use App\Repositories\Interfaces\UserRewardRepositoryInterface;
use App\Services\Account\AddressService;
use App\Services\Checkout\CheckoutPromotions;
use App\Services\Checkout\CheckoutTotalService;
use App\Services\Checkout\PromotionService;
use App\Services\Checkout\ShippingFeeService;
use App\Services\ConfigDbService;
use App\Services\Currency\CurrencyService;
use Mockery;
use ReflectionMethod;
use Tests\TestCase;

class CheckoutTotalServiceTest extends TestCase
{
    protected $fakeConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeConfig = new class () extends ConfigDbService {
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

    protected function makeService(
        ?UserRewardRepositoryInterface $rewardRepo = null,
        array $voucherResult = ['voucherApplied' => []],
    ): CheckoutTotalService {
        $promo = Mockery::mock(PromotionService::class);
        $promo->shouldReceive('resolveVouchers')->andReturn($voucherResult);

        return new CheckoutTotalService(
            Mockery::mock(ShippingFeeService::class),
            $rewardRepo ?? Mockery::mock(UserRewardRepositoryInterface::class),
            $promo,
            Mockery::mock(AddressService::class),
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

        $this->assertSame(0, $total);
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

        $this->assertSame(20000, $m->invoke($svc, new Coupon(['discount_max' => 20000, 'discount' => 0])));
        $this->assertSame(15000, $m->invoke($svc, new Coupon(['discount_max' => 0, 'discount' => 15000])));
    }

    public function test_freeship_ship_discount_kep_theo_cap(): void
    {
        $svc = $this->makeService();
        $m = new ReflectionMethod($svc, 'freeshipShipDiscount');

        $entry = ['coupon' => new Coupon(['discount_max' => 20000, 'discount' => 0])];

        $this->assertSame(20000, $m->invoke($svc, $entry, 30000));
        $this->assertSame(30000, $m->invoke($svc, ['coupon' => new Coupon(['discount_max' => 0, 'discount' => 0])], 30000)); // cap 0 -> free toàn bộ
        $this->assertSame(0, $m->invoke($svc, null, 30000));
        $this->assertSame(0, $m->invoke($svc, $entry, 0));
    }

    public function test_extract_address_lay_dung_dia_chi_default_tu_address_service(): void
    {
        $addressService = Mockery::mock(AddressService::class);
        $addressService->shouldReceive('resolveDisplayList')->once()->andReturn([
            ['id' => 1, 'full_address' => 'A', 'is_default' => 0],
            ['id' => 2, 'full_address' => 'B', 'is_default' => 1],
        ]);

        $svc = new CheckoutTotalService(
            Mockery::mock(ShippingFeeService::class),
            Mockery::mock(UserRewardRepositoryInterface::class),
            Mockery::mock(PromotionService::class),
            $addressService,
        );
        $m = new ReflectionMethod($svc, 'extractAddress');

        $this->assertSame('B', $m->invoke($svc)['full_address']);
    }

    public function test_extract_address_fallback_dia_chi_dau_khi_khong_co_default(): void
    {
        $addressService = Mockery::mock(AddressService::class);
        $addressService->shouldReceive('resolveDisplayList')->andReturn([
            ['id' => 1, 'full_address' => 'Chỉ có 1 địa chỉ', 'is_default' => 0],
        ]);

        $svc = new CheckoutTotalService(
            Mockery::mock(ShippingFeeService::class),
            Mockery::mock(UserRewardRepositoryInterface::class),
            Mockery::mock(PromotionService::class),
            $addressService,
        );
        $m = new ReflectionMethod($svc, 'extractAddress');

        $this->assertSame('Chỉ có 1 địa chỉ', $m->invoke($svc)['full_address']);
    }

    public function test_extract_address_rong_khi_chua_co_dia_chi_nao(): void
    {
        $addressService = Mockery::mock(AddressService::class);
        $addressService->shouldReceive('resolveDisplayList')->andReturn([]);

        $svc = new CheckoutTotalService(
            Mockery::mock(ShippingFeeService::class),
            Mockery::mock(UserRewardRepositoryInterface::class),
            Mockery::mock(PromotionService::class),
            $addressService,
        );
        $m = new ReflectionMethod($svc, 'extractAddress');

        $this->assertSame([], $m->invoke($svc));
    }

    public function test_gift_line_hien_thi_dung_so_luong_qua_tang(): void
    {
        session()->put(getCoreConfig('session.applied_gifts'), [
            ['item_ids' => [1, 2]],
            ['item_ids' => [3]],
        ]);

        $promotions = (new CheckoutPromotions())
            ->setItems([['total' => 100000]])
            ->setAppliedCoupons([], false);

        [$totalData, $total] = $this->makeService()->build($promotions, withShipping: false);

        $this->assertSame(100000, $total);
        $this->assertSame(0, $this->lineValue($totalData, 'gifts'));
        $this->assertNotNull(collect($totalData)->firstWhere('code', 'gifts'));
    }

    public function test_gift_line_khong_hien_thi_khi_khong_co_gift(): void
    {
        $promotions = (new CheckoutPromotions())
            ->setItems([['total' => 100000]])
            ->setAppliedCoupons([], false);

        [$totalData] = $this->makeService()->build($promotions, withShipping: false);

        $this->assertNull($this->lineValue($totalData, 'gifts'));
    }

    public function test_gift_line_bo_qua_entry_thieu_item_ids(): void
    {
        session()->put(getCoreConfig('session.applied_gifts'), [
            ['note' => 'khong co item_ids'],
        ]);

        $promotions = (new CheckoutPromotions())
            ->setItems([['total' => 100000]])
            ->setAppliedCoupons([], false);

        [$totalData] = $this->makeService()->build($promotions, withShipping: false);

        $this->assertNull($this->lineValue($totalData, 'gifts'));
    }

    public function test_voucher_tru_tien_dung_so_tien_ap_dung(): void
    {
        $voucherResult = [
            'voucherApplied' => [
                ['voucher' => new Voucher(['code' => 'GIFT10']), 'amount' => 20000],
            ],
        ];

        $promotions = (new CheckoutPromotions())
            ->setItems([['total' => 100000]])
            ->setAppliedCoupons([], false);

        [$totalData, $total] = $this->makeService(voucherResult: $voucherResult)
            ->build($promotions, withShipping: false);

        $this->assertSame(80000, $total);
        $this->assertSame(-20000, $this->lineValue($totalData, 'voucher:GIFT10'));
    }

    public function test_voucher_amount_0_khong_tao_dong_giam(): void
    {
        $voucherResult = [
            'voucherApplied' => [
                ['voucher' => new Voucher(['code' => 'EMPTY']), 'amount' => 0],
            ],
        ];

        $promotions = (new CheckoutPromotions())
            ->setItems([['total' => 100000]])
            ->setAppliedCoupons([], false);

        [$totalData, $total] = $this->makeService(voucherResult: $voucherResult)
            ->build($promotions, withShipping: false);

        $this->assertSame(100000, $total);
        $this->assertNull($this->lineValue($totalData, 'voucher:EMPTY'));
    }

    public function test_voucher_nhieu_voucher_cung_ap_dung(): void
    {
        $voucherResult = [
            'voucherApplied' => [
                ['voucher' => new Voucher(['code' => 'V1']), 'amount' => 10000],
                ['voucher' => new Voucher(['code' => 'V2']), 'amount' => 5000],
            ],
        ];

        $promotions = (new CheckoutPromotions())
            ->setItems([['total' => 100000]])
            ->setAppliedCoupons([], false);

        [$totalData, $total] = $this->makeService(voucherResult: $voucherResult)
            ->build($promotions, withShipping: false);

        $this->assertSame(85000, $total);
        $this->assertSame(-10000, $this->lineValue($totalData, 'voucher:V1'));
        $this->assertSame(-5000, $this->lineValue($totalData, 'voucher:V2'));
    }

    public function test_voucher_khong_duoc_goi_khi_total_da_ve_0(): void
    {
        $promo = Mockery::mock(PromotionService::class);
        $promo->shouldNotReceive('resolveVouchers');

        $svc = new CheckoutTotalService(
            Mockery::mock(ShippingFeeService::class),
            Mockery::mock(UserRewardRepositoryInterface::class),
            $promo,
            Mockery::mock(AddressService::class),
        );

        $promotions = (new CheckoutPromotions())
            ->setItems([['total' => 50000]])
            ->setAppliedCoupons([
                ['coupon' => new Coupon(['code' => 'BIG', 'type' => 1]), 'discount' => 999999, 'type' => 1],
            ], false);

        [$totalData, $total] = $svc->build($promotions, withShipping: false);

        $this->assertSame(0, $total);
        $this->assertNull($this->lineValue($totalData, 'voucher:BIG'));
    }
}
