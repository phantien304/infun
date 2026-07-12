<?php

namespace Tests\Unit\Services;

use App\Models\Entities\Voucher;
use App\Repositories\Interfaces\VoucherRepositoryInterface;
use App\Services\Cart\VoucherService;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

/**
 * Tier 1 — luật voucher (gift card): VoucherService::validate.
 *
 * Kiểm 4 nhánh reject (inactive / expired / used_up / invalid_order) + happy path.
 * status.active = 1 lấy từ config core (getCoreConfig('voucher.status.active')).
 * availableBalance() = max(0, amount - redeemed_balance) — thuần, không DB.
 */
class VoucherServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function service(): VoucherService
    {
        // validate không gọi repo.
        return new VoucherService(Mockery::mock(VoucherRepositoryInterface::class));
    }

    protected function makeVoucher(array $attrs = []): Voucher
    {
        return new Voucher(array_merge([
            'code'             => 'GC-100',
            'status'           => 1,      // active
            'amount'           => 100000,
            'redeemed_balance' => 0,
            'date_expire'      => null,
        ], $attrs));
    }

    public function test_voucher_hop_le_tra_null(): void
    {
        $this->assertNull(
            $this->service()->validate($this->makeVoucher(), orderTotal: 200000),
        );
    }

    public function test_status_khong_active_bi_tu_choi(): void
    {
        $this->assertNotNull(
            $this->service()->validate($this->makeVoucher(['status' => 4]), orderTotal: 200000),
        );
    }

    public function test_het_han_bi_tu_choi(): void
    {
        $this->assertNotNull(
            $this->service()->validate(
                $this->makeVoucher(['date_expire' => Carbon::now()->subDay()->toDateString()]),
                orderTotal: 200000,
            ),
        );
    }

    public function test_con_han_thi_pass(): void
    {
        $this->assertNull(
            $this->service()->validate(
                $this->makeVoucher(['date_expire' => Carbon::now()->addDay()->toDateString()]),
                orderTotal: 200000,
            ),
        );
    }

    public function test_het_so_du_bi_tu_choi(): void
    {
        $this->assertNotNull(
            $this->service()->validate(
                $this->makeVoucher(['amount' => 100000, 'redeemed_balance' => 100000]),
                orderTotal: 200000,
            ),
        );
    }

    public function test_don_hang_khong_duong_bi_tu_choi(): void
    {
        $this->assertNotNull(
            $this->service()->validate($this->makeVoucher(), orderTotal: 0),
        );
    }
}
