<?php

namespace Tests\Unit\Services;

use App\Models\Entities\Product;
use App\Services\ConfigDbService;
use App\Services\Reward\RewardEarnService;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Tier 2 — điểm thưởng tích được / đơn vị: RewardEarnService::perUnit.
 *
 * Ưu tiên: tắt reward -> 0; có productReward theo user_group -> điểm cố định;
 * không có -> intdiv(price, divisor); divisor 0 -> 0.
 * getUserGroupId() (không đăng nhập, console) -> getConfigDb('config_user_group_id').
 */
class RewardEarnServiceTest extends TestCase
{
    protected $fakeConfig;

    protected function setUp(): void
    {
        parent::setUp();

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
    }

    protected function makeProduct(array $rewards = []): Product
    {
        $product = new Product();
        $product->setRelation('productRewards', new Collection(array_map(
            fn ($r) => (object) $r,
            $rewards,
        )));

        return $product;
    }

    public function test_tat_reward_tra_0(): void
    {
        // enabled() = getConfigDb('config_reward_point_enabled') != setting('reward_point.disable')(=0)
        // -> để 0 == 0 nên enabled() false.
        $this->fakeConfig->data = ['config_reward_point_enabled' => 0];

        $this->assertSame(0, (new RewardEarnService())->perUnit($this->makeProduct(), 100000));
    }

    public function test_uu_tien_diem_cau_hinh_theo_user_group(): void
    {
        $this->fakeConfig->data = [
            'config_reward_point_enabled' => 1,
            'config_user_group_id'        => 1,
        ];

        $product = $this->makeProduct([
            ['user_group_id' => 1, 'points' => 50],
            ['user_group_id' => 2, 'points' => 999],
        ]);

        $this->assertSame(50, (new RewardEarnService())->perUnit($product, 100000));
    }

    public function test_khong_co_cau_hinh_thi_chia_theo_divisor(): void
    {
        $this->fakeConfig->data = [
            'config_reward_point_enabled' => 1,
            'config_user_group_id'        => 1,
            'config_reward_earn_divisor'  => 1000,
        ];

        // row points = 0 (đúng group) -> rơi xuống nhánh divisor: 25000 / 1000 = 25
        $product = $this->makeProduct([['user_group_id' => 1, 'points' => 0]]);
        $this->assertSame(25, (new RewardEarnService())->perUnit($product, 25000));
    }

    public function test_divisor_0_tra_0(): void
    {
        $this->fakeConfig->data = [
            'config_reward_point_enabled' => 1,
            'config_user_group_id'        => 1,
            'config_reward_earn_divisor'  => 0,
        ];

        $product = $this->makeProduct([['user_group_id' => 1, 'points' => 0]]);
        $this->assertSame(0, (new RewardEarnService())->perUnit($product, 25000));
    }
}
