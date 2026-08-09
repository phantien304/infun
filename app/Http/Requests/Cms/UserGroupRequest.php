<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class UserGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $defaultLang = getConfigDb('config_language') ?: 'vi';

        $rules = [
            'approval'   => 'nullable|numeric|in:0,1',
            'sort_order' => 'nullable|numeric',
            'user_group_descriptions'                 => 'array',
            'user_group_descriptions.*.language_code' => 'required|string|max:11',
            'user_group_descriptions.*.description'   => 'nullable|string',
        ];

        foreach ((array) $this->input('user_group_descriptions', []) as $i => $item) {
            $rules["user_group_descriptions.$i.name"] =
                (($item['language_code'] ?? '') === $defaultLang)
                    ? 'required|string|max:32'
                    : 'nullable|string|max:32';
        }

        return $rules;
    }
}
