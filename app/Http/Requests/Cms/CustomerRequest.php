<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate cho store + update Customer CMS (chỉ member, type=2 — theo yêu
 * cầu user "Đầy đủ CRUD như màn User admin"). Mirror UserRequest (Phase 2.3)
 * nhưng KHÔNG có username/role_ids, THÊM phone/address/sex/newsletter/
 * user_group_id (field riêng của Customer, xem CustomerRepositoryInterface).
 * password required lúc tạo, optional lúc sửa (rỗng = giữ nguyên).
 */
class CustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Quyền đã chặn ở middleware (auth:sanctum + cms.permission).
        return true;
    }

    public function rules(): array
    {
        $userId   = $this->route('customer')?->id;
        $isCreate = $userId === null;

        return [
            'email'         => ['required', 'email', 'max:256', Rule::unique('user', 'email')->ignore($userId)],
            'password'      => [$isCreate ? 'required' : 'nullable', 'string', 'min:8', 'max:256'],
            'full_name'     => 'required|string|max:255',
            'avatar'        => 'nullable|string|max:255',
            'phone'         => 'nullable|string|max:32',
            'address'       => 'nullable|string|max:500',
            'sex'           => 'nullable|integer|in:0,1',
            'newsletter'    => 'nullable|boolean',
            'user_group_id' => ['nullable', 'integer', Rule::exists('user_group', 'id')->whereNull('deleted_at')],
            'status'        => 'required|numeric|in:0,1',
        ];
    }
}
