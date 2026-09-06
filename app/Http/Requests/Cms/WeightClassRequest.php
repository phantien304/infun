<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class WeightClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $defaultLang = getConfigDb('config_language') ?: 'vi';

        $rules = [
            'value'                                    => 'required|numeric',
            'weight_class_descriptions'                => 'array',
            'weight_class_descriptions.*.language_code' => 'required|string|max:11',
        ];

        foreach ((array) $this->input('weight_class_descriptions', []) as $i => $item) {
            $isDefault = ($item['language_code'] ?? '') === $defaultLang;
            $rules["weight_class_descriptions.$i.title"] = $isDefault ? 'required|string|max:32' : 'nullable|string|max:32';
            $rules["weight_class_descriptions.$i.unit"] = $isDefault ? 'required|string|max:4' : 'nullable|string|max:4';
        }

        return $rules;
    }
}
