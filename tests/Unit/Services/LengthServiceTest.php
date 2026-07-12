<?php

namespace Tests\Unit\Services;

use App\Models\Entities\LengthClass;
use App\Repositories\Interfaces\LengthClassRepositoryInterface;
use App\Services\ConfigDbService;
use App\Services\Measurement\LengthService;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

/**
 * Tier 2 — quy đổi đơn vị chiều dài: LengthService.
 * Cùng công thức convert như WeightService; format() luôn 2 chữ số thập phân.
 */
class LengthServiceTest extends TestCase
{
    protected $fakeConfig;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fakeConfig = new class extends ConfigDbService
        {
            public array $data = [
                'config_language_admin'  => 'vi',
                'config_length_class_id' => 2,
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

    /** id 1 = value 1.0 (mm) ; id 2 = value 10.0 (cm) */
    protected function service(): LengthService
    {
        $classes = new Collection([
            $this->makeClass(1, 1.0, 'mm'),
            $this->makeClass(2, 10.0, 'cm'),
        ]);

        $repo = Mockery::mock(LengthClassRepositoryInterface::class);
        $repo->shouldReceive('listAllCached')->andReturn($classes);

        return new LengthService($repo);
    }

    protected function makeClass(int $id, float $value, string $unit): LengthClass
    {
        $lc = (new LengthClass())->forceFill(['id' => $id, 'value' => $value]);
        $lc->setRelation('descriptions', new Collection([
            (object) ['language_code' => 'vi', 'unit' => $unit, 'title' => "cls-{$id}"],
        ]));

        return $lc;
    }

    public function test_convert_theo_ty_le_value(): void
    {
        $this->assertEqualsWithDelta(50.0, $this->service()->convert(5.0, 1, 2), 0.0001);
        $this->assertEqualsWithDelta(5.0, $this->service()->convert(50.0, 2, 1), 0.0001);
    }

    public function test_convert_cung_class_giu_nguyen(): void
    {
        $this->assertSame(7.0, $this->service()->convert(7.0, 2, 2));
    }

    public function test_convert_thieu_class_thi_giu_nguyen(): void
    {
        $this->assertSame(7.0, $this->service()->convert(7.0, 1, 99));
    }

    public function test_convert_to_system_dung_config_id(): void
    {
        $this->assertEqualsWithDelta(50.0, $this->service()->convertToSystem(5.0, 1), 0.0001);
    }

    public function test_format_hai_chu_so_thap_phan(): void
    {
        $this->assertSame('12.50cm', $this->service()->format(12.5, 2));
    }
}
