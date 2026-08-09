<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MenuValueRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $defaultLang = getConfigDb('config_language') ?: 'vi';

        $rules = [
            'menu_id'     => 'required|integer',
            'item_id'     => 'nullable|integer',
            'parent_id'   => 'nullable|integer',
            'position'    => 'nullable|integer',
            'type'        => ['required', Rule::in(['page', 'category', 'information', 'product', 'blogCategory'])],
            'css'         => 'nullable|string|max:255',
            'html_custom' => 'nullable|string',
            'mega_menu'   => 'nullable|numeric',
            'tab_content' => 'nullable|numeric',
            'image'       => 'nullable|string|max:255',
            'menu_value_descriptions'                 => 'array',
            'menu_value_descriptions.*.language_code' => 'required|string|max:11',
            'menu_value_descriptions.*.link'           => 'nullable|string|max:255',
        ];

        foreach ((array) $this->input('menu_value_descriptions', []) as $i => $item) {
            $rules["menu_value_descriptions.$i.title"] =
                (($item['language_code'] ?? '') === $defaultLang)
                    ? 'required|string|max:255'
                    : 'nullable|string|max:255';
        }

        return $rules;
    }
}
