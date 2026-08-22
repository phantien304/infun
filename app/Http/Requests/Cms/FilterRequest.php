<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class FilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $defaultLang = getConfigDb('config_language') ?: 'vi';

        $rules = [
            'sort_order' => 'nullable|numeric',

            'filter_descriptions'                 => 'array',
            'filter_descriptions.*.language_code' => 'required|string|max:11',

            'filter_values'                                              => 'array',
            'filter_values.*.id'                                         => 'nullable|integer',
            'filter_values.*.sort_order'                                 => 'nullable|numeric',
            'filter_values.*.filter_value_descriptions.*.language_code' => 'required|string|max:11',
        ];

        foreach ((array) $this->input('filter_descriptions', []) as $i => $item) {
            $rules["filter_descriptions.$i.name"] =
                (($item['language_code'] ?? '') === $defaultLang)
                    ? 'required|string|max:64'
                    : 'nullable|string|max:64';
        }

        foreach ((array) $this->input('filter_values', []) as $i => $value) {
            foreach ((array) ($value['filter_value_descriptions'] ?? []) as $j => $desc) {
                $rules["filter_values.$i.filter_value_descriptions.$j.name"] =
                    (($desc['language_code'] ?? '') === $defaultLang)
                        ? 'required|string|max:64'
                        : 'nullable|string|max:64';
            }
        }

        return $rules;
    }
}
