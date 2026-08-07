<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate cho store + update User CMS (chỉ admin, type=1 — Phase 6.1).
 * password required lúc tạo, optional lúc sửa (rỗng = giữ nguyên mật khẩu
 * cũ) — mirror UserValidator._buildCreateRules/_buildUpdateRules ở mt219.
 */
class UserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Quyền đã chặn ở middleware (auth:sanctum + cms.permission).
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;
        $isCreate = $userId === null;

        return [
            'username'  => ['nullable', 'string', 'max:256', Rule::unique('user', 'username')->ignore($userId)],
            'email'     => ['required', 'email', 'max:256', Rule::unique('user', 'email')->ignore($userId)],
            'password'  => [$isCreate ? 'required' : 'nullable', 'string', 'min:8', 'max:256'],
            'full_name' => 'required|string|max:255',
            'avatar'    => 'nullable|string|max:255',
            'status'    => 'required|numeric|in:0,1',
            'role_ids'   => 'array',
            'role_ids.*' => 'integer',
        ];
    }
}
