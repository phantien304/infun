<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate form đặt lại mật khẩu (qua link token). Tính hợp lệ của token
 * (email + code) do AuthController kiểm tra trước qua AuthService, request
 * này chỉ lo password + xác nhận khớp.
 */
class AuthResetPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'password'         => 'required|string|min:6|max:50',
            'confirm_password' => 'required|string|same:password',
        ];
    }

    public function messages(): array
    {
        return [
            'password.required'         => trans('messages.auth.validation.password_required'),
            'password.min'              => trans('messages.auth.validation.password_min'),
            'confirm_password.required' => trans('messages.auth.validation.confirm_required'),
            'confirm_password.same'     => trans('messages.auth.validation.confirm_mismatch'),
        ];
    }
}
