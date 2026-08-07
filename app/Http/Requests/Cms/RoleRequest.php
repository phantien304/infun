<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate cho store + update Role (spatie). `permission_ids` chỉ validate
 * SHAPE (mảng số nguyên) ở đây — lọc theo quyền thực có của user hiện tại
 * (chống leo thang) nằm ở App\Services\Cms\RoleWriteService, KHÔNG phải
 * việc của FormRequest.
 */
class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Quyền đã chặn ở middleware (auth:sanctum + cms.permission).
        return true;
    }

    public function rules(): array
    {
        $rolesTable = config('permission.table_names.roles') ?: 'sp_roles';

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique($rolesTable, 'name')
                    ->where('guard_name', 'web')
                    ->ignore($this->route('role')?->id),
            ],
            'permission_ids'   => 'array',
            'permission_ids.*' => 'integer',
        ];
    }
}
