<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReviewCriteriaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $defaultLang = getConfigDb('config_language') ?: 'vi';
        // Laravel số ít hoá "review-criteria" -> route param "review_criterion".
        $criteriaId  = $this->route('review_criterion')?->id;

        $rules = [
            'code' => [
                'required', 'string', 'max:32',
                Rule::unique('review_criteria', 'code')->ignore($criteriaId),
            ],
            'icon'        => 'nullable|string|max:64',
            'sort_order'  => 'nullable|numeric',
            'is_required' => 'required|boolean',
            'is_active'   => 'required|boolean',

            'review_criteria_descriptions'                 => 'array',
            'review_criteria_descriptions.*.language_code' => 'required|string|max:11',
            'review_criteria_descriptions.*.hint'          => 'nullable|string|max:255',
        ];

        foreach ((array) $this->input('review_criteria_descriptions', []) as $i => $item) {
            $rules["review_criteria_descriptions.$i.name"] =
                (($item['language_code'] ?? '') === $defaultLang)
                    ? 'required|string|max:100'
                    : 'nullable|string|max:100';
        }

        return $rules;
    }
}
