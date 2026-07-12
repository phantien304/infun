<?php

namespace Tests\Unit\Services;

use App\Models\Entities\Gift;
use App\Repositories\Interfaces\GiftRepositoryInterface;
use App\Repositories\Interfaces\OrderGiftRepositoryInterface;
use App\Services\Cart\GiftService;
use App\Services\ConfigDbService;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

/**
 * Tier 2 — luật quà tặng: GiftService::validateTrigger + validatePicks.
 *
 * Enum lấy từ config core: trigger_type(min_subtotal=1, buy_specific=2),
 * pick_type(auto=0, pick_1_of_n=1, pick_up_to_n=2).
 * Không đụng DB: Gift dựng in-memory, items/triggerProducts set bằng setRelation.
 */
class GiftServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

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

    protected function service(): GiftService
    {
        return new GiftService(
            Mockery::mock(GiftRepositoryInterface::class),
            Mockery::mock(OrderGiftRepositoryInterface::class),
        );
    }

    protected function makeGift(array $attrs = [], array $relations = []): Gift
    {
        $gift = new Gift($attrs);

        foreach ($relations as $name => $items) {
            $gift->setRelation($name, new Collection($items));
        }

        return $gift;
    }

    /** items dạng object có id (validatePicks pluck('id')). */
    protected function items(array $ids): array
    {
        return array_map(fn ($id) => (object) ['id' => $id], $ids);
    }

    // ---- validateTrigger ----

    public function test_min_subtotal_du_dieu_kien_tra_null(): void
    {
        $gift = $this->makeGift(['trigger_type' => 1, 'min_subtotal' => 100000]);

        $this->assertNull($this->service()->validateTrigger($gift, 200000, []));
    }

    public function test_min_subtotal_thieu_tien_bi_tu_choi(): void
    {
        $gift = $this->makeGift(['trigger_type' => 1, 'min_subtotal' => 100000]);

        $this->assertNotNull($this->service()->validateTrigger($gift, 50000, []));
    }

    public function test_min_subtotal_null_luon_pass(): void
    {
        $gift = $this->makeGift(['trigger_type' => 1, 'min_subtotal' => null]);

        $this->assertNull($this->service()->validateTrigger($gift, 0, []));
    }

    public function test_buy_specific_co_sp_trong_gio_pass(): void
    {
        $gift = $this->makeGift(
            ['trigger_type' => 2],
            ['triggerProducts' => [(object) ['product_id' => 10]]],
        );

        $this->assertNull($this->service()->validateTrigger($gift, 0, [10, 20]));
    }

    public function test_buy_specific_khong_co_sp_bi_tu_choi(): void
    {
        $gift = $this->makeGift(
            ['trigger_type' => 2],
            ['triggerProducts' => [(object) ['product_id' => 99]]],
        );

        $this->assertNotNull($this->service()->validateTrigger($gift, 0, [10, 20]));
    }

    // ---- validatePicks ----

    public function test_chon_qua_ngoai_danh_sach_bi_tu_choi(): void
    {
        $gift = $this->makeGift(['pick_type' => 1], ['items' => $this->items([1, 2])]);

        $this->assertNotNull($this->service()->validatePicks($gift, [999]));
    }

    public function test_auto_phai_nhan_tat_ca(): void
    {
        $gift = $this->makeGift(['pick_type' => 0], ['items' => $this->items([1, 2])]);

        $this->assertNotNull($this->service()->validatePicks($gift, [1]));      // thiếu
        $this->assertNull($this->service()->validatePicks($gift, [1, 2]));      // đủ
    }

    public function test_pick_1_of_n_phai_dung_1(): void
    {
        $gift = $this->makeGift(['pick_type' => 1], ['items' => $this->items([1, 2, 3])]);

        $this->assertNull($this->service()->validatePicks($gift, [2]));         // đúng 1
        $this->assertNotNull($this->service()->validatePicks($gift, [1, 2]));   // 2 -> sai
    }

    public function test_pick_up_to_n_ton_trong_gioi_han(): void
    {
        $gift = $this->makeGift(
            ['pick_type' => 2, 'pick_limit' => 2],
            ['items' => $this->items([1, 2, 3])],
        );

        $this->assertNull($this->service()->validatePicks($gift, [1, 2]));         // trong hạn
        $this->assertNotNull($this->service()->validatePicks($gift, [1, 2, 3]));   // vượt 2
    }
}
