<?php

namespace Tests\Unit\Services;

use App\Enums\VoucherStatus;
use App\Helpers\Facades\ChannelLog;
use App\Jobs\VoucherRewardSendEmailJob;
use App\Models\Entities\Orders;
use App\Models\Entities\Voucher;
use App\Models\Entities\VoucherRewardGrant;
use App\Models\Entities\VoucherRewardRule;
use App\Repositories\Interfaces\VoucherRepositoryInterface;
use App\Repositories\Interfaces\VoucherRewardGrantRepositoryInterface;
use App\Repositories\Interfaces\VoucherRewardRuleRepositoryInterface;
use App\Services\ConfigDbService;
use App\Services\Voucher\VoucherRewardService;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

/**
 * Bước 6 — luồng tặng/thu hồi voucher theo giá trị đơn (VoucherRewardService).
 *
 * Unit thuần: mock cả 3 repo (rule/grant/voucher) — không đụng DB.
 * `transaction()` được mock để CHẠY thẳng closure (tái hiện DB::transaction).
 * getConfigDb() trong createVoucher đọc qua ConfigDbService → bind stub để không
 * chạm DB. Job gửi mail → Bus::fake() chặn, chỉ assert đã dispatch.
 *
 * Bao phủ: đủ/không đủ ngưỡng, phát đôi (observer bắn 2 lần), reactivate khi
 * complete lại, rollback khi trạng thái đổi giữa chừng, hết quota, đua
 * duplicate-key, max_per_user, khách vãng lai đếm theo email, revoke khi hủy,
 * revoke bỏ qua khi voucher đã dùng.
 */
class VoucherRewardServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // logInfo()/logError() đi qua facade ChannelLog — spy để thành no-op,
        // test không phụ thuộc channel log thật.
        ChannelLog::spy();
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // ---- factories ----------------------------------------------------------

    /** @return array{0:MockInterface,1:MockInterface,2:MockInterface} */
    private function repos(): array
    {
        return [
            Mockery::mock(VoucherRewardRuleRepositoryInterface::class),
            Mockery::mock(VoucherRewardGrantRepositoryInterface::class),
            Mockery::mock(VoucherRepositoryInterface::class),
        ];
    }

    private function service(MockInterface $ruleRepo, MockInterface $grantRepo, MockInterface $voucherRepo): VoucherRewardService
    {
        return new VoucherRewardService($ruleRepo, $grantRepo, $voucherRepo);
    }

    private function rule(array $attrs = []): VoucherRewardRule
    {
        return new VoucherRewardRule(array_merge([
            'id'                 => 10,
            'name'               => 'Đơn 1tr tặng 50k',
            'min_order_total'    => 1_000_000,
            'reward_amount'      => 50_000,
            'reward_expire_days' => 30,
            'max_per_user'       => null,
            'quota_total'        => null,
            'granted_count'      => 0,
            'status'             => 1,
        ], $attrs));
    }

    private function order(array $attrs = []): Orders
    {
        $order = new Orders();
        foreach (array_merge([
            'id'        => 1,
            'email'     => 'kh@example.com',
            'total'     => 1_000_000,
            'user_id'   => 7,
            'full_name' => 'Nguyen Van A',
        ], $attrs) as $key => $value) {
            $order->{$key} = $value;
        }

        return $order;
    }

    private function voucher(int $status = VoucherStatus::Active->value, float $redeemed = 0.0, int $id = 555): Voucher
    {
        $voucher = new Voucher([
            'code'             => 'ABCD2345EFGH',
            'status'           => $status,
            'amount'           => 50_000,
            'redeemed_balance' => $redeemed,
        ]);
        $voucher->id = $id;

        return $voucher;
    }

    private function grant(Voucher $voucher, int $ruleId = 10): VoucherRewardGrant
    {
        $grant = new VoucherRewardGrant([
            'rule_id'    => $ruleId,
            'order_id'   => 1,
            'voucher_id' => $voucher->id,
        ]);
        $grant->setRelation('voucher', $voucher);

        return $grant;
    }

    /** transaction() mock: chạy thẳng closure như DB::transaction. */
    private function runsTransaction(MockInterface $ruleRepo): void
    {
        $ruleRepo->shouldReceive('transaction')->andReturnUsing(fn (\Closure $cb) => $cb());
    }

    private function stubConfig(): void
    {
        $this->app->instance(ConfigDbService::class, Mockery::mock(ConfigDbService::class, [
            'getConfigs' => ['config_name' => 'Shop', 'config_email' => 'shop@example.com'],
        ]));
    }

    // ---- grant --------------------------------------------------------------

    public function test_don_duoi_nguong_khong_duoc_tang(): void
    {
        [$ruleRepo, $grantRepo, $voucherRepo] = $this->repos();
        $grantRepo->shouldReceive('rewardGrantsForOrder')->andReturn(collect());
        $ruleRepo->shouldReceive('listRunningRewardRules')->andReturn(collect([$this->rule()]));

        $increment = false;
        $ruleRepo->shouldReceive('incrementRewardRuleCount')->andReturnUsing(function () use (&$increment) {
            $increment = true;

            return 1;
        });

        $this->service($ruleRepo, $grantRepo, $voucherRepo)
            ->grantForOrder($this->order(['total' => 999_000]));

        $this->assertFalse($increment, 'Đơn dưới min_order_total không được chiếm quota.');
    }

    public function test_du_nguong_tang_voucher_va_gui_mail(): void
    {
        Bus::fake();
        $this->stubConfig();

        [$ruleRepo, $grantRepo, $voucherRepo] = $this->repos();
        $grantRepo->shouldReceive('rewardGrantsForOrder')->andReturn(collect());
        $ruleRepo->shouldReceive('listRunningRewardRules')->andReturn(collect([$this->rule()]));
        $ruleRepo->shouldReceive('incrementRewardRuleCount')->andReturn(1);
        $this->runsTransaction($ruleRepo);
        $voucherRepo->shouldReceive('findByCode')->andReturn(null);
        $voucherRepo->shouldReceive('flushCache');

        $createdVoucher = null;
        $voucherRepo->shouldReceive('createVoucher')->andReturnUsing(function (array $data) use (&$createdVoucher) {
            $createdVoucher = $data;

            return $this->voucher();
        });

        $createdGrant = null;
        $grantRepo->shouldReceive('createRewardGrant')->andReturnUsing(function (array $data) use (&$createdGrant) {
            $createdGrant = $data;

            return $this->grant($this->voucher());
        });

        $this->service($ruleRepo, $grantRepo, $voucherRepo)
            ->grantForOrder($this->order(['email' => 'KH@Example.com']));

        Bus::assertDispatched(VoucherRewardSendEmailJob::class);
        $this->assertSame(50_000.0, (float) $createdVoucher['amount']);
        $this->assertSame(VoucherStatus::Active->value, $createdVoucher['status']);
        $this->assertNull($createdVoucher['order_id'], 'Voucher tặng KHÔNG set order_id.');
        $this->assertSame('kh@example.com', $createdVoucher['to_email'], 'Email chuẩn hoá lowercase.');
        $this->assertSame(555, $createdGrant['voucher_id']);
        $this->assertSame(10, $createdGrant['rule_id']);
        $this->assertSame(1, $createdGrant['order_id']);
    }

    public function test_observer_ban_hai_lan_khong_phat_doi(): void
    {
        [$ruleRepo, $grantRepo, $voucherRepo] = $this->repos();
        // Grant đã tồn tại, voucher vẫn Active → không làm gì thêm.
        $grantRepo->shouldReceive('rewardGrantsForOrder')->andReturn(collect([$this->grant($this->voucher())]));
        $ruleRepo->shouldReceive('listRunningRewardRules')->andReturn(collect([$this->rule()]));

        $increment = false;
        $ruleRepo->shouldReceive('incrementRewardRuleCount')->andReturnUsing(function () use (&$increment) {
            $increment = true;

            return 1;
        });

        $this->service($ruleRepo, $grantRepo, $voucherRepo)->grantForOrder($this->order());

        $this->assertFalse($increment, 'Grant đã có + voucher Active → không chiếm quota lần nữa.');
    }

    public function test_reactivate_khi_don_complete_lai(): void
    {
        [$ruleRepo, $grantRepo, $voucherRepo] = $this->repos();
        $grantRepo->shouldReceive('rewardGrantsForOrder')
            ->andReturn(collect([$this->grant($this->voucher(VoucherStatus::Revoked->value))]));
        $ruleRepo->shouldReceive('listRunningRewardRules')->andReturn(collect([$this->rule()]));
        $ruleRepo->shouldReceive('incrementRewardRuleCount')->andReturn(1);
        $this->runsTransaction($ruleRepo);
        $voucherRepo->shouldReceive('flushCache');

        $reactivated = null;
        $voucherRepo->shouldReceive('reactivateRevoked')->andReturnUsing(function (array $ids, int $from, int $to) use (&$reactivated) {
            $reactivated = compact('ids', 'from', 'to');

            return 1;
        });

        $this->service($ruleRepo, $grantRepo, $voucherRepo)->grantForOrder($this->order());

        $this->assertNotNull($reactivated, 'Voucher Revoked chưa dùng → phải reactivate.');
        $this->assertSame([555], $reactivated['ids']);
        $this->assertSame(VoucherStatus::Revoked->value, $reactivated['from']);
        $this->assertSame(VoucherStatus::Active->value, $reactivated['to']);
    }

    public function test_reactivate_rollback_khi_trang_thai_da_doi(): void
    {
        [$ruleRepo, $grantRepo, $voucherRepo] = $this->repos();
        $grantRepo->shouldReceive('rewardGrantsForOrder')
            ->andReturn(collect([$this->grant($this->voucher(VoucherStatus::Revoked->value))]));
        $ruleRepo->shouldReceive('listRunningRewardRules')->andReturn(collect([$this->rule()]));
        $ruleRepo->shouldReceive('incrementRewardRuleCount')->andReturn(1);
        $this->runsTransaction($ruleRepo);
        $voucherRepo->shouldReceive('reactivateRevoked')->andReturn(0); // tiến trình khác đổi trước

        $flushed = false;
        $voucherRepo->shouldReceive('flushCache')->andReturnUsing(function () use (&$flushed) {
            $flushed = true;
        });

        // Sentinel exception phải bị nuốt bên trong service — không văng ra ngoài.
        $this->service($ruleRepo, $grantRepo, $voucherRepo)->grantForOrder($this->order());

        $this->assertFalse($flushed, 'reactivateRevoked=0 → rollback, không flush cache.');
    }

    public function test_het_quota_khong_tao_grant(): void
    {
        Bus::fake();

        [$ruleRepo, $grantRepo, $voucherRepo] = $this->repos();
        $grantRepo->shouldReceive('rewardGrantsForOrder')->andReturn(collect());
        $ruleRepo->shouldReceive('listRunningRewardRules')
            ->andReturn(collect([$this->rule(['quota_total' => 5, 'granted_count' => 5])]));
        $ruleRepo->shouldReceive('incrementRewardRuleCount')->andReturn(0); // hết suất
        $this->runsTransaction($ruleRepo);

        $created = false;
        $grantRepo->shouldReceive('createRewardGrant')->andReturnUsing(function () use (&$created) {
            $created = true;

            return $this->grant($this->voucher());
        });

        $this->service($ruleRepo, $grantRepo, $voucherRepo)->grantForOrder($this->order());

        $this->assertFalse($created, 'Hết quota → không insert grant.');
        Bus::assertNotDispatched(VoucherRewardSendEmailJob::class);
    }

    public function test_dua_duplicate_key_bi_nuot(): void
    {
        Bus::fake();

        [$ruleRepo, $grantRepo, $voucherRepo] = $this->repos();
        $grantRepo->shouldReceive('rewardGrantsForOrder')->andReturn(collect());
        $ruleRepo->shouldReceive('listRunningRewardRules')->andReturn(collect([$this->rule()]));
        // Transaction văng duplicate-key (uq_vrg_rule_order) — tiến trình khác nhanh hơn.
        $ruleRepo->shouldReceive('transaction')->andThrow($this->duplicateKeyException());

        // Không được ném ra ngoài.
        $this->service($ruleRepo, $grantRepo, $voucherRepo)->grantForOrder($this->order());

        Bus::assertNotDispatched(VoucherRewardSendEmailJob::class);
    }

    public function test_max_per_user_chan_tang_them(): void
    {
        [$ruleRepo, $grantRepo, $voucherRepo] = $this->repos();
        $grantRepo->shouldReceive('rewardGrantsForOrder')->andReturn(collect());
        $ruleRepo->shouldReceive('listRunningRewardRules')->andReturn(collect([$this->rule(['max_per_user' => 1])]));
        $grantRepo->shouldReceive('countRewardGrantsForUser')->andReturn(1); // đã đạt trần

        $increment = false;
        $ruleRepo->shouldReceive('incrementRewardRuleCount')->andReturnUsing(function () use (&$increment) {
            $increment = true;

            return 1;
        });

        $this->service($ruleRepo, $grantRepo, $voucherRepo)->grantForOrder($this->order());

        $this->assertFalse($increment, 'Đạt max_per_user → không tặng thêm.');
    }

    public function test_khach_vang_lai_dem_theo_email(): void
    {
        [$ruleRepo, $grantRepo, $voucherRepo] = $this->repos();
        $grantRepo->shouldReceive('rewardGrantsForOrder')->andReturn(collect());
        $ruleRepo->shouldReceive('listRunningRewardRules')->andReturn(collect([$this->rule(['max_per_user' => 2])]));
        $ruleRepo->shouldReceive('incrementRewardRuleCount')->andReturn(1);

        $args = null;
        $grantRepo->shouldReceive('countRewardGrantsForUser')->andReturnUsing(function ($ruleId, $userId, $email) use (&$args) {
            $args = compact('ruleId', 'userId', 'email');

            return 2; // đạt trần → dừng, đủ để kiểm tham số đếm
        });

        $this->service($ruleRepo, $grantRepo, $voucherRepo)
            ->grantForOrder($this->order(['user_id' => 0, 'email' => 'GUEST@Example.com']));

        $this->assertNotNull($args);
        $this->assertNull($args['userId'], 'Khách vãng lai (user_id 0/NULL) → đếm theo email.');
        $this->assertSame('guest@example.com', $args['email']);
    }

    // ---- revoke -------------------------------------------------------------

    public function test_revoke_thu_hoi_voucher_chua_dung(): void
    {
        [$ruleRepo, $grantRepo, $voucherRepo] = $this->repos();
        $grantRepo->shouldReceive('rewardGrantsForOrder')->andReturn(collect([$this->grant($this->voucher())]));
        $this->runsTransaction($ruleRepo);
        $voucherRepo->shouldReceive('revokeUnused')->andReturn(1);
        $voucherRepo->shouldReceive('flushCache');

        $decrement = null;
        $ruleRepo->shouldReceive('decrementRewardRuleCount')->andReturnUsing(function ($ruleId, $by) use (&$decrement) {
            $decrement = compact('ruleId', 'by');
        });

        $this->service($ruleRepo, $grantRepo, $voucherRepo)->revokeForOrder($this->order());

        $this->assertNotNull($decrement, 'Revoke thành công phải trả suất quota.');
        $this->assertSame(10, $decrement['ruleId']);
        $this->assertSame(1, $decrement['by']);
    }

    public function test_revoke_bo_qua_khi_voucher_da_dung(): void
    {
        [$ruleRepo, $grantRepo, $voucherRepo] = $this->repos();
        // redeemed_balance > 0 → khách đã tiêu, không tự thu hồi.
        $grantRepo->shouldReceive('rewardGrantsForOrder')
            ->andReturn(collect([$this->grant($this->voucher(VoucherStatus::Active->value, 20_000.0))]));

        $revoked = false;
        $voucherRepo->shouldReceive('revokeUnused')->andReturnUsing(function () use (&$revoked) {
            $revoked = true;

            return 1;
        });
        $decrement = false;
        $ruleRepo->shouldReceive('decrementRewardRuleCount')->andReturnUsing(function () use (&$decrement) {
            $decrement = true;
        });

        $this->service($ruleRepo, $grantRepo, $voucherRepo)->revokeForOrder($this->order());

        $this->assertFalse($revoked, 'Voucher đã dùng một phần → không revoke.');
        $this->assertFalse($decrement, 'Không trả suất quota khi đã tiêu.');
    }

    // ---- helpers ------------------------------------------------------------

    private function duplicateKeyException(): QueryException
    {
        $exception = new QueryException(
            'mysql',
            'insert into `voucher_reward_grant` ...',
            [],
            new RuntimeException('Duplicate entry'),
        );
        // errorInfo là public (kế thừa PDOException); [1] = mã lỗi driver.
        $exception->errorInfo = ['23000', 1062, 'Duplicate entry'];

        return $exception;
    }
}
