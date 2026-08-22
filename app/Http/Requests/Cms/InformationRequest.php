<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class InformationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $defaultLang = getConfigDb('config_language') ?: 'vi';

        $rules = [
            'banner_id'  => 'nullable|numeric',
            'sort_order' => 'nullable|numeric',

            'information_descriptions'                       => 'array',
            'information_descriptions.*.language_code'       => 'required|string|max:11',
            'information_descriptions.*.description'         => 'nullable|string',
            'information_descriptions.*.content'             => 'nullable|string',
            'information_descriptions.*.meta_title'          => 'nullable|string|max:255',
            'information_descriptions.*.meta_description'    => 'nullable|string|max:255',
        ];

        foreach ((array) $this->input('information_descriptions', []) as $i => $item) {
            $rules["information_descriptions.$i.title"] =
                (($item['language_code'] ?? '') === $defaultLang)
                    ? 'required|string|max:255'
                    : 'nullable|string|max:255';
        }

        return $rules;
    }
}
