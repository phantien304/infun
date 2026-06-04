<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Thay validator legacy `UserValidator::validateUpdateUser`. Tách 3 field
 * cơ bản (full_name / sex / address) + phone (lưu ở bảng user_phone, không
 * cột user.phone). Email read-only ở blade — không validate.
 */
class AccountUpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'full_name' => 'required|string|max:255',
            'sex'       => 'nullable|in:0,1',
            'address'   => 'nullable|string|max:500',
            'phone'     => 'nullable|string|min:8|max:15',
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required' => trans('messages.ErrorFullName'),
            'phone.min'          => trans('messages.ErrorPhone'),
            'phone.max'          => trans('messages.ErrorPhone'),
        ];
    }
}
