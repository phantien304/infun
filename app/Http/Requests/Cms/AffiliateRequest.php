<?php

namespace App\Http\Requests\Cms;

use App\Enums\AffiliateStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Admin sửa hồ sơ KOL.
 *
 * KHÔNG có `code` và `clicks_count` trong rules — cố ý: mã ref đã phát tán
 * ra ngoài (link, story đã đăng), đổi là gãy attribution; clicks_count là
 * aggregate do tracking ghi.
 *
 * `commission_rate` nullable = dùng rate global. Trần 100 vì đây là phần
 * trăm; để trống khác hẳn với đặt 0 (0% nghĩa là KOL không được đồng nào).
 */
class AffiliateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'status'          => ['required', Rule::in(array_column(AffiliateStatus::cases(), 'value'))],
            'commission_rate' => 'nullable|numeric|min:0|max:100',

            'payment_info'                 => 'nullable|array',
            'payment_info.bank_name'       => 'nullable|string|max:100',
            'payment_info.bank_account'    => 'nullable|string|max:30',
            'payment_info.bank_holder'     => 'nullable|string|max:100',
            'payment_info.zalopay_phone'   => 'nullable|string|max:15',
        ];
    }
}
