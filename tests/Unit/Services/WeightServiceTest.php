<?php

namespace Tests\Unit\Services;

use App\Models\Entities\WeightClass;
use App\Repositories\Interfaces\WeightClassRepositoryInterface;
use App\Services\ConfigDbService;
use App\Services\Measurement\WeightService;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

/**
 * Tier 2 — quy đổi đơn vị khối lượng: WeightService.
 *
 * convert() = value * (toValue / fromValue); có guard chia 0 / thiếu class.
 * Repo listAllCached() mock -> không chạm DB; ngôn ngữ resolve = 'batch' (console)
 * -> getConfigDb('config_language_admin') qua fake.
 */
class WeightServiceTest extends TestCase
{
    protected $fakeConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeConfig = new class extends ConfigDbService
        {
            public array $data = [
                'config_language_admin'  => 'vi',
                'config_weight_class_id' => 2, // "hệ thống" = class id 2
            ];

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

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    /** id 1 = value 1.0 ; id 2 = value 1000.0 */
    protected function service(): WeightService
    {
        $classes = new Collection([
            $this->makeClass(1, 1.0, 'g'),
            $this->makeClass(2, 1000.0, 'kg'),
        ]);

        $repo = Mockery::mock(WeightClassRepositoryInterface::class);
        $repo->shouldReceive('listAllCached')->andReturn($classes);

        return new WeightService($repo);
    }

    protected function makeClass(int $id, float $value, string $unit): WeightClass
    {
        $wc = (new WeightClass())->forceFill(['id' => $id, 'value' => $value]);
        $wc->setRelation('descriptions', new Collection([
            (object) ['language_code' => 'vi', 'unit' => $unit, 'title' => "cls-{$id}"],
        ]));

        return $wc;
    }

    public function test_convert_theo_ty_le_value(): void
    {
        $this->assertEqualsWithDelta(2000.0, $this->service()->convert(2.0, 1, 2), 0.0001);
        $this->assertEqualsWithDelta(2.0, $this->service()->convert(2000.0, 2, 1), 0.0001);
    }

    public function test_convert_cung_class_giu_nguyen(): void
    {
        $this->assertSame(5.0, $this->service()->convert(5.0, 1, 1));
    }

    public function test_convert_thieu_class_thi_giu_nguyen(): void
    {
        // class 99 không tồn tại -> toValue = 0 -> guard trả nguyên giá trị.
        $this->assertSame(5.0, $this->service()->convert(5.0, 1, 99));
    }

    public function test_convert_to_system_dung_config_id(): void
    {
        // system id = 2 -> convert(2, 1, 2) = 2000.
        $this->assertEqualsWithDelta(2000.0, $this->service()->convertToSystem(2.0, 1), 0.0001);
    }

    public function test_get_unit_va_format(): void
    {
        $this->assertSame('kg', $this->service()->getUnit(2));
        $this->assertSame('1,000kg', $this->service()->format(1000.0, 2));
    }
}
