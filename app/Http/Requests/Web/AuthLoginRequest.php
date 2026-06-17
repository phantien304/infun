<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate form đăng nhập. Ràng buộc nghiệp vụ (confirmed/status/type) do
 * AuthService::login + Auth::attempt xử lý — request này chỉ check input.
 */
class AuthLoginRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email'    => 'required|email',
            'password' => 'required|string',
        ];
    }

    public function messages(): array
    {
        return [
            'email.required'    => trans('messages.auth.validation.email_required'),
            'email.email'       => trans('messages.auth.validation.email_invalid'),
            'password.required' => trans('messages.auth.validation.password_required'),
        ];
    }
}
