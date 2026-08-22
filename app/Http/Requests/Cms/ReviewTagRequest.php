<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewTagRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $defaultLang = getConfigDb('config_language') ?: 'vi';
        $tagId       = $this->route('review_tag')?->id;

        $rules = [
            'code' => [
                'required', 'string', 'max:64',
                Rule::unique('review_tag', 'code')->ignore($tagId),
            ],
            'is_auto_generated' => 'required|boolean',
            'is_active'         => 'required|boolean',

            'review_tag_descriptions'                 => 'array',
            'review_tag_descriptions.*.language_code' => 'required|string|max:11',
        ];

        foreach ((array) $this->input('review_tag_descriptions', []) as $i => $item) {
            $rules["review_tag_descriptions.$i.name"] =
                (($item['language_code'] ?? '') === $defaultLang)
                    ? 'required|string|max:100'
                    : 'nullable|string|max:100';
        }

        return $rules;
    }
}
