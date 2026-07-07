<?php

namespace App\Console\Commands;

use App\Enums\OptionRole;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Seed custom field (role=custom_field) cho các product HIỆN CÓ — tách khỏi
 * `variants:seed` (không đụng cluster variant). Tự tạo option + option_value
 * nếu chưa có.
 *
 * Ghi vào schema mới:
 *  - product_option        (product_id, option_id, value, required, price, sort_order, ts)
 *      → surrogate id + UNIQUE(product_id, option_id) ⇒ insertOrIgnore dedupe.
 *  - product_option_value  (product_option_id, option_value_id, image, price, sort_order, ts)
 *      → KHÔNG còn product_id/option_id; product_option_id resolve SAU khi insert
 *        declaration; UNIQUE(product_option_id, option_value_id) ⇒ insertOrIgnore.
 *
 * `--price`: set giá mẫu để test phụ phí cart (CartService::customOptionsSurcharge):
 *  - field nhập tự do (text/…): product_option.price.
 *  - field picker (radio/select): product_option_value.price của từng lựa chọn.
 */
class SeedCustomFieldsCommand extends Command
{
    protected $signature = 'custom-fields:seed
        {--percent=100 : % product được gắn custom field (0–100)}
        {--per-product=2 : Số custom field tối đa mỗi product (random 1..N)}
        {--chunk=200 : Số product mỗi batch}
        {--price : Set giá mẫu cho field/lựa chọn (test phụ phí cart)}
        {--truncate : Xóa custom field [SEED] (product_option + picker) trước khi seed}';

    protected $description = 'Seed custom field options + gắn cho product hiện có (schema product_option mới)';

    /**
     * Pool custom field [SEED]. `price` = phụ phí (VND) khi --price bật.
     * `values` = [ [tên, giá], ... ] cho picker; rỗng cho field nhập tự do.
     */
    private const CUSTOM_FIELDS = [
        ['name' => '[SEED] Lời chúc in áo',   'type' => 'text',     'default' => 'Chúc mừng sinh nhật', 'required' => 0, 'price' => 20000, 'values' => []],
        ['name' => '[SEED] Email người nhận', 'type' => 'email',    'default' => '',   'required' => 0, 'price' => 0, 'values' => []],
        ['name' => '[SEED] SĐT giao hàng',    'type' => 'phone',    'default' => '',   'required' => 1, 'price' => 0, 'values' => []],
        ['name' => '[SEED] Ghi chú đơn hàng', 'type' => 'textarea', 'default' => '',   'required' => 0, 'price' => 0, 'values' => []],
        ['name' => '[SEED] Chọn dây áo',      'type' => 'radio',    'default' => null, 'required' => 0, 'price' => 0, 'values' => [['Dây đen', 0], ['Dây nâu', 30000], ['Dây đỏ', 50000]]],
        ['name' => '[SEED] Chọn loại in',     'type' => 'select',   'default' => null, 'required' => 0, 'price' => 0, 'values' => [['In lụa', 0], ['In nhiệt', 15000]]],
    ];

    public function handle(): int
    {
        $percent    = max(0, min(100, (int) $this->option('percent')));
        $perProduct = max(1, (int) $this->option('per-product'));
        $chunk      = max(50, (int) $this->option('chunk'));
        $withPrice  = (bool) $this->option('price');

        $totalProducts = (int) DB::table('product')->whereNull('deleted_at')->count();
        if ($totalProducts === 0) {
            $this->warn('Bảng product rỗng — chạy `php artisan products:seed` trước.');
            return self::FAILURE;
        }

        $options = $this->ensureOptions($withPrice);
        $optionIds = array_keys($options);
        $this->info(sprintf('Đã đảm bảo %d custom field option [SEED].', count($optionIds)));

        if ($this->option('truncate')) {
            $this->truncate($optionIds);
        }

        $this->info("Gắn custom field cho ~{$percent}% trong {$totalProducts} product (1–{$perProduct} field/product)…");
        $bar = $this->output->createProgressBar($totalProducts);
        $bar->start();

        $totalDecl = $totalPicker = 0;

        DB::table('product')
            ->whereNull('deleted_at')
            ->select('id')
            ->orderBy('id')
            ->chunk($chunk, function ($products) use ($percent, $perProduct, $withPrice, $options, $optionIds, &$totalDecl, &$totalPicker, $bar) {
                $now = Carbon::now();
                $declarations = [];
                $pickerPlan   = []; // giữ product_id/option_id để resolve product_option_id sau

                foreach ($products as $product) {
                    if (rand(1, 100) > $percent) {
                        continue;
                    }

                    $shuffled = $optionIds;
                    shuffle($shuffled);
                    $picked = array_slice($shuffled, 0, rand(1, min($perProduct, count($shuffled))));

                    foreach ($picked as $sort => $optionId) {
                        $cf = $options[$optionId];
                        $isPicker = ! empty($cf['value_ids']);

                        $declarations[] = [
                            'product_id' => $product->id,
                            'option_id'  => $optionId,
                            'value'      => $cf['default'],
                            'required'   => $cf['required'],
                            'price'      => ($withPrice && ! $isPicker) ? $cf['price'] : 0,
                            'sort_order' => 10 + $sort,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        foreach ($cf['value_ids'] as $povSort => [$valueId, $valuePrice]) {
                            $pickerPlan[] = [
                                'product_id'      => $product->id,
                                'option_id'       => $optionId,
                                'option_value_id' => $valueId,
                                'price'           => $withPrice ? $valuePrice : 0,
                                'sort_order'      => $povSort,
                                'created_at'      => $now,
                                'updated_at'      => $now,
                            ];
                        }
                    }
                }

                if ($declarations) {
                    DB::table('product_option')->insertOrIgnore($declarations);
                    $totalDecl += count($declarations);
                }

                if ($pickerPlan) {
                    // product_option_value.product_option_id NOT NULL → resolve id
                    // của declaration vừa insert theo (product_id, option_id).
                    $poMap = DB::table('product_option')
                        ->whereIn('product_id', array_values(array_unique(array_column($pickerPlan, 'product_id'))))
                        ->whereIn('option_id', $optionIds)
                        ->get(['id', 'product_id', 'option_id'])
                        ->keyBy(fn ($r) => $r->product_id . ':' . $r->option_id);

                    $rows = [];
                    foreach ($pickerPlan as $pv) {
                        $po = $poMap->get($pv['product_id'] . ':' . $pv['option_id']);
                        if (! $po) {
                            continue;
                        }
                        $rows[] = [
                            'product_option_id' => (int) $po->id,
                            'option_value_id'   => $pv['option_value_id'],
                            'image'             => null,
                            'price'             => $pv['price'],
                            'sort_order'        => $pv['sort_order'],
                            'created_at'        => $pv['created_at'],
                            'updated_at'        => $pv['updated_at'],
                        ];
                    }

                    if ($rows) {
                        DB::table('product_option_value')->insertOrIgnore($rows);
                        $totalPicker += count($rows);
                    }
                }

                $bar->advance(count($products));
            });

        $bar->finish();
        $this->newLine();
        $this->info("Xong: {$totalDecl} declaration + {$totalPicker} picker value" . ($withPrice ? ' (có giá mẫu).' : '.'));

        return self::SUCCESS;
    }

    /**
     * Đảm bảo option + option_value tồn tại. Trả [optionId => [type, default,
     * required, price, value_ids => [[valueId, price], ...]]].
     */
    private function ensureOptions(bool $withPrice): array
    {
        $result = [];
        foreach (self::CUSTOM_FIELDS as $cf) {
            $optionId = $this->upsertOption($cf['name'], $cf['type']);

            $valueIds = [];
            foreach ($cf['values'] as [$valueName, $valuePrice]) {
                $valueIds[] = [$this->upsertOptionValue($optionId, $valueName), $withPrice ? $valuePrice : 0];
            }

            $result[$optionId] = [
                'type'      => $cf['type'],
                'default'   => $cf['default'],
                'required'  => $cf['required'],
                'price'     => $cf['price'],
                'value_ids' => $valueIds,
            ];
        }

        return $result;
    }

    /** Tìm option theo description.name; nếu chưa có → tạo option + description (vi). */
    private function upsertOption(string $name, string $type): int
    {
        $existing = DB::table('option as o')
            ->join('option_description as od', 'od.option_id', '=', 'o.id')
            ->where('od.name', $name)
            ->whereNull('o.deleted_at')
            ->value('o.id');

        if ($existing) {
            return (int) $existing;
        }

        $now = Carbon::now();
        $optionId = DB::table('option')->insertGetId([
            'type'       => $type,
            'role'       => OptionRole::CustomField->value,
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('option_description')->insert([
            'option_id'     => $optionId,
            'language_code' => 'vi',
            'name'          => $name,
            'name_display'  => $name,
        ]);

        return (int) $optionId;
    }

    /** Tìm option_value theo (option_id, description.name); nếu chưa có → tạo. */
    private function upsertOptionValue(int $optionId, string $name): int
    {
        $existing = DB::table('option_value as ov')
            ->join('option_value_description as ovd', 'ovd.option_value_id', '=', 'ov.id')
            ->where('ov.option_id', $optionId)
            ->where('ovd.name', $name)
            ->value('ov.id');

        if ($existing) {
            return (int) $existing;
        }

        $now = Carbon::now();
        $valueId = DB::table('option_value')->insertGetId([
            'option_id'  => $optionId,
            'image'      => null,
            'sort_order' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        DB::table('option_value_description')->insert([
            'option_value_id' => $valueId,
            'language_code'   => 'vi',
            'name'            => $name,
        ]);

        return (int) $valueId;
    }

    /**
     * Xóa declaration + picker của custom field [SEED]. Hard delete qua DB::table
     * (bỏ qua soft-delete). Picker xóa trước (qua product_option_id) rồi declaration.
     * KHÔNG đụng option/option_value gốc để giữ idempotent.
     */
    private function truncate(array $optionIds): void
    {
        $deletedPicker = DB::table('product_option_value')
            ->whereIn('product_option_id', function ($q) use ($optionIds) {
                $q->select('id')->from('product_option')->whereIn('option_id', $optionIds);
            })
            ->delete();

        $deletedDecl = DB::table('product_option')
            ->whereIn('option_id', $optionIds)
            ->delete();

        $this->line("  xóa {$deletedDecl} product_option + {$deletedPicker} product_option_value ([SEED] custom field)");
    }
}
