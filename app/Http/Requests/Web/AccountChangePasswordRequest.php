<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Thay validator legacy `UserValidator::validateChangeUserPassword`.
 *
 * Quy ước:
 *  - user có `type_register` (social login: facebook/google) → không cần
 *    `old_password` (vì user chưa từng đặt password). Logic check trong
 *    `withValidator->after` để bypass có điều kiện.
 *  - user thường (type_register rỗng) → bắt buộc nhập `old_password` đúng
 *    với hash hiện tại.
 *  - `confirm_password` phải khớp `password`.
 */
class AccountChangePasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $user = $this->user();
        $needOld = ! filled($user?->type_register ?? null);

        return [
            'old_password'     => [Rule::requiredIf($needOld), 'nullable', 'string'],
            'password'         => 'required|string|min:6|max:50',
            'confirm_password' => 'required|string|same:password',
        ];
    }

    public function messages(): array
    {
        return [
            'old_password.required'     => trans('messages.ErrorOldPassword'),
            'password.required'         => trans('messages.ErrorPassword'),
            'password.min'              => trans('messages.ErrorPassword'),
            'confirm_password.required' => trans('messages.ErrorConfirmPassword'),
            'confirm_password.same'     => trans('messages.ErrorConfirmPasswordNotMatch'),
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v) {
            $user = $this->user();
            if (! $user) {
                return;
            }
            // Social-login user (type_register != null): bỏ qua check
            // old_password vì user chưa từng set.
            if (filled($user->type_register ?? null)) {
                return;
            }
            $old = (string) $this->input('old_password', '');
            if ($old === '' || ! Hash::check($old, (string) $user->password)) {
                $v->errors()->add('old_password', trans('messages.ErrorOldPasswordIncorrect'));
            }
        });
    }
}
