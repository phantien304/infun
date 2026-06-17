<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate form quên mật khẩu — chỉ cần email hợp lệ. Việc email có thuộc
 * khách hàng nào hay không do AuthService::sendResetLink kiểm tra.
 */
class AuthForgotPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required' => trans('messages.auth.validation.email_required'),
            'email.email'    => trans('messages.auth.validation.email_invalid'),
        ];
    }
}
