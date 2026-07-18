<?php

namespace Database\Seeders;

use App\Enums\VoucherRewardRuleStatus;
use App\Models\Entities\VoucherRewardRule;
use Illuminate\Database\Seeder;

class VoucherRewardRuleSeeder extends Seeder
{
    public function run(): void
    {
        VoucherRewardRule::firstOrCreate(
            ['name' => 'Đơn từ 1.000.000đ tặng voucher 50.000đ'],
            [
                'description'        => 'Tự động tặng voucher 50k cho đơn hoàn tất có tổng ≥ 1 triệu; voucher dùng cho các đơn sau.',
                'min_order_total'    => 1_000_000,
                'reward_amount'      => 50_000,
                'reward_expire_days' => 30,
                'max_per_user'       => null,   // NULL = không giới hạn số lần / khách
                'quota_total'        => null,   // NULL = không giới hạn ngân sách; đặt số để chặn
                'granted_count'      => 0,
                'date_start'         => null,   // NULL = hiệu lực ngay
                'date_end'           => null,   // NULL = không hết hạn theo lịch
                'status'             => VoucherRewardRuleStatus::Active->value,
                'sort_order'         => 0,
            ],
        );
    }
}
