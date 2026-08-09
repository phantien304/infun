<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RoleRequest extends FormRequest
{
    public function authorize(): bool
    {
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
