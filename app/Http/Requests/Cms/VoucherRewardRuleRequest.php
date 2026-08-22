<?php

namespace App\Http\Requests\Cms;

use App\Enums\VoucherRewardRuleStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate quy tắc tặng voucher theo giá trị đơn — màn MỚI.
 *
 * `reward_expire_days` ≥ 1: voucher phát ra hết hạn ngay trong ngày thì
 * chương trình vô nghĩa. KHÔNG nhận `granted_count` — bộ đếm quota chống
 * đua, chỉ repository ghi bằng conditional UPDATE.
 */
class VoucherRewardRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'               => 'required|string|max:128',
            'description'        => 'nullable|string',
            'min_order_total'    => 'required|numeric|min:0',
            'reward_amount'      => 'required|numeric|min:1',
            'reward_expire_days' => 'required|integer|min:1',
            'max_per_user'       => 'nullable|integer|min:1',
            'quota_total'        => 'nullable|integer|min:1',
            'date_start'         => 'nullable|date',
            'date_end'           => 'nullable|date|after_or_equal:date_start',
            'status'             => ['required', Rule::in(array_column(VoucherRewardRuleStatus::cases(), 'value'))],
            'sort_order'         => 'nullable|integer|min:0',
        ];
    }
}
