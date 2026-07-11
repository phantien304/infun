<?php

namespace Tests\Feature;

use App\Models\Base\Base;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Chống tái phát double json-encode (bug 2026-07-11, affiliate.payment_info):
 * Base::save() refill raw attributes → cast array/json encode LẦN 2 nếu
 * setAttribute không idempotent. Fix: Base::setAttribute gán thẳng chuỗi
 * JSON hợp lệ cho cột json-castable. Probe bằng model ẩn danh để test
 * chính đường refill, không phụ thuộc model nghiệp vụ nào.
 */
class BaseJsonCastTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('json_probe', function ($t) {
            $t->integer('id', true);
            $t->text('payload')->nullable();
            $t->string('name', 50)->nullable();
        });
    }

    protected function probeModel(): Base
    {
        return new class extends Base {
            protected $table = 'json_probe';
            protected $primaryKeyAutoIncrement = 'id';
            public $timestamps = false;
            protected $casts = ['payload' => 'array'];
        };
    }

    public function test_json_cast_khong_double_encode_qua_base_save(): void
    {
        $model = $this->probeModel();
        $row = $model::create([
            'payload' => ['bank_name' => 'VCB', 'note' => 'tiếng Việt ✓'],
            'name'    => 'probe',
        ]);

        // Raw trong DB phải decode 1 LẦN ra array (không phải string JSON lồng).
        $raw = DB::table('json_probe')->where('id', $row->id)->value('payload');
        $decoded = json_decode((string) $raw, true);
        $this->assertIsArray($decoded, 'payload bị double-encode: ' . $raw);
        $this->assertSame('VCB', $decoded['bank_name']);

        // Đọc qua model cũng ra array đúng.
        $fresh = $model::query()->find($row->id);
        $this->assertIsArray($fresh->payload);
        $this->assertSame('tiếng Việt ✓', $fresh->payload['note']);
    }

    public function test_update_lai_van_khong_double_encode(): void
    {
        $model = $this->probeModel();
        $row = $model::create(['payload' => ['a' => 1]]);

        // Update qua save() lần nữa (đi qua refill lần 2).
        $found = $model::query()->find($row->id);
        $found->name = 'updated';
        $found->save();

        $decoded = json_decode((string) DB::table('json_probe')->where('id', $row->id)->value('payload'), true);
        $this->assertIsArray($decoded);
        $this->assertSame(1, $decoded['a']);
    }

    public function test_set_array_moi_van_encode_binh_thuong(): void
    {
        $model = $this->probeModel();
        $row = $model::create(['payload' => ['a' => 1]]);

        $found = $model::query()->find($row->id);
        $found->payload = ['b' => 2]; // array mới → encode 1 lần như thường
        $found->save();

        $this->assertSame(['b' => 2], $model::query()->find($row->id)->payload);
    }

    public function test_null_giu_nguyen(): void
    {
        $model = $this->probeModel();
        $row = $model::create(['payload' => null, 'name' => 'x']);

        $this->assertNull($model::query()->find($row->id)->payload);
    }
}
