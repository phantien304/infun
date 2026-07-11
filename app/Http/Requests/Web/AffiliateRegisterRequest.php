<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /account/affiliate/register` — user đăng ký làm affiliate (Phase 4).
 * Bắt buộc đồng ý điều khoản + thông tin nhận tiền (payout chuyển khoản
 * trước — business #4 đã chốt; zalopay_phone optional cho tích hợp chi hộ sau).
 */
class AffiliateRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'agree'         => 'accepted',
            'bank_name'     => 'required|string|max:100',
            'bank_account'  => 'required|string|max:30|regex:/^[0-9 ]+$/',
            'bank_holder'   => 'required|string|max:100',
            'zalopay_phone' => 'nullable|string|max:15|regex:/^[0-9+]+$/',
        ];
    }

    public function messages(): array
    {
        return [
            'agree.accepted'         => trans('messages.affiliate.error_agree'),
            'bank_name.required'     => trans('messages.affiliate.error_bank_name'),
            'bank_account.required'  => trans('messages.affiliate.error_bank_account'),
            'bank_account.regex'     => trans('messages.affiliate.error_bank_account'),
            'bank_holder.required'   => trans('messages.affiliate.error_bank_holder'),
            'zalopay_phone.regex'    => trans('messages.affiliate.error_zalopay_phone'),
        ];
    }

    /** payment_info JSON lưu vào bảng affiliate. */
    public function paymentInfo(): array
    {
        $info = [
            'bank_name'    => trim((string) $this->input('bank_name')),
            'bank_account' => trim((string) $this->input('bank_account')),
            'bank_holder'  => mb_strtoupper(trim((string) $this->input('bank_holder'))),
        ];
        if (filled($this->input('zalopay_phone'))) {
            $info['zalopay_phone'] = trim((string) $this->input('zalopay_phone'));
        }

        return $info;
    }
}
