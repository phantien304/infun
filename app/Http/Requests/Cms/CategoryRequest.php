<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class CategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $defaultLang = getConfigDb('config_language') ?: 'vi';

        $rules = [
            'parent_id'  => 'nullable|numeric',
            'sort_order' => 'nullable|numeric',
            'icon'       => 'nullable|string|max:255',
            'image'      => 'nullable|string|max:255',
            'image_icon' => 'nullable|string|max:255',
            'category_descriptions'                    => 'array',
            'category_descriptions.*.language_code'    => 'required|string|max:11',
            'category_descriptions.*.meta_title'       => 'nullable|string|max:255',
            'category_descriptions.*.meta_description' => 'nullable|string|max:255',
        ];

        foreach ((array) $this->input('category_descriptions', []) as $i => $item) {
            $rules["category_descriptions.$i.title"] =
                (($item['language_code'] ?? '') === $defaultLang)
                    ? 'required|string|max:255'
                    : 'nullable|string|max:255';
        }

        return $rules;
    }
}
