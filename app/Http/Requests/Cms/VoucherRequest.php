<?php

namespace App\Http\Requests\Cms;

use App\Enums\VoucherStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate voucher (gift card) từ CMS.
 *
 * Khác mt219 (`VoucherValidator`): code max 20 (cũ 10 — brute-force quá rẻ),
 * thêm `status`, `date_expire`. KHÔNG nhận `redeemed_balance` (sổ tiền đã
 * dùng, chỉ VoucherRepository::incrementRedeemed ghi) và `sent_at` (job gửi
 * mail đóng dấu).
 */
class VoucherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $voucherId = $this->route('voucher')?->id;

        return [
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('voucher', 'code')->ignore($voucherId),
            ],
            'order_id'         => 'nullable|integer|exists:orders,id',
            'from_name'        => 'required|string|max:64',
            'from_email'       => 'required|email|max:96',
            'to_name'          => 'required|string|max:64',
            'to_email'         => 'required|email|max:96',
            'voucher_theme_id' => 'required|integer|exists:voucher_theme,id',
            'message'          => 'nullable|string',
            'amount'           => 'required|numeric|min:0',
            'status'           => ['required', Rule::in(array_column(VoucherStatus::cases(), 'value'))],
            'date_expire'      => 'nullable|date',
        ];
    }
}
