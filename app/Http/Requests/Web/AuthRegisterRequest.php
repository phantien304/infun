<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate form đăng ký khách hàng. Email phải chưa tồn tại (bỏ qua bản ghi
 * đã soft-delete để cho phép đăng ký lại email cũ). Phone lưu ở bảng
 * `user_phone` nên không validate unique trên `user`.
 */
class AuthRegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => [
                'required',
                'email',
                Rule::unique('user', 'email')->whereNull('deleted_at'),
            ],
            'password'          => 'required|string|min:6|max:50',
            'full_name'         => 'required|string|max:255',
            'phone'             => 'required|string|min:8|max:15',
            'nation_phone_code' => 'nullable|string|max:8',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'     => trans('messages.auth.validation.email_required'),
            'email.email'        => trans('messages.auth.validation.email_invalid'),
            'email.unique'       => trans('messages.auth.validation.email_exists'),
            'password.required'  => trans('messages.auth.validation.password_required'),
            'password.min'       => trans('messages.auth.validation.password_min'),
            'full_name.required' => trans('messages.auth.validation.full_name_required'),
            'phone.required'     => trans('messages.auth.validation.phone_required'),
            'phone.min'          => trans('messages.auth.validation.phone_invalid'),
            'phone.max'          => trans('messages.auth.validation.phone_invalid'),
        ];
    }
}
