<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class BlogTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $defaultLang = getConfigDb('config_language') ?: 'vi';

        $rules = [
            'background' => 'nullable|string|max:255',
            'sort_order' => 'nullable|numeric',
            'blog_tag_descriptions'                    => 'array',
            'blog_tag_descriptions.*.language_code'    => 'required|string|max:11',
            'blog_tag_descriptions.*.meta_title'       => 'nullable|string|max:255',
            'blog_tag_descriptions.*.meta_description' => 'nullable|string|max:255',
        ];

        foreach ((array) $this->input('blog_tag_descriptions', []) as $i => $item) {
            $rules["blog_tag_descriptions.$i.title"] =
                (($item['language_code'] ?? '') === $defaultLang)
                    ? 'required|string|max:255'
                    : 'nullable|string|max:255';
        }

        return $rules;
    }
}
