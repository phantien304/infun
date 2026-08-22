<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class AttributeRequest extends FormRequest
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

            'attribute_descriptions'                 => 'array',
            'attribute_descriptions.*.language_code' => 'required|string|max:11',

            'attribute_values'                                                 => 'array',
            'attribute_values.*.id'                                            => 'nullable|integer',
            'attribute_values.*.sort_order'                                    => 'nullable|numeric',
            'attribute_values.*.attribute_value_descriptions.*.language_code' => 'required|string|max:11',
        ];

        foreach ((array) $this->input('attribute_descriptions', []) as $i => $item) {
            $rules["attribute_descriptions.$i.name"] =
                (($item['language_code'] ?? '') === $defaultLang)
                    ? 'required|string|max:64'
                    : 'nullable|string|max:64';
        }

        foreach ((array) $this->input('attribute_values', []) as $i => $value) {
            foreach ((array) ($value['attribute_value_descriptions'] ?? []) as $j => $desc) {
                $rules["attribute_values.$i.attribute_value_descriptions.$j.name"] =
                    (($desc['language_code'] ?? '') === $defaultLang)
                        ? 'required|string|max:64'
                        : 'nullable|string|max:64';
            }
        }

        return $rules;
    }
}
