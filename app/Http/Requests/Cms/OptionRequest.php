<?php

namespace App\Http\Requests\Cms;

use App\Enums\OptionRole;
use App\Enums\OptionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $defaultLang = getConfigDb('config_language') ?: 'vi';

        $rules = [
            'type'       => ['required', 'string', Rule::in(array_column(OptionType::cases(), 'value'))],
            'role'       => ['required', 'integer', Rule::in(array_column(OptionRole::cases(), 'value'))],
            'sort_order' => 'nullable|numeric',

            'option_descriptions'                 => 'array',
            'option_descriptions.*.language_code' => 'required|string|max:11',
            'option_descriptions.*.name_display'  => 'nullable|string|max:128',

            'option_values'                                              => 'array',
            'option_values.*.id'                                         => 'nullable|integer',
            'option_values.*.image'                                      => 'nullable|string|max:255',
            'option_values.*.sort_order'                                 => 'nullable|numeric',
            'option_values.*.option_value_descriptions.*.language_code' => 'required|string|max:11',
        ];

        foreach ((array) $this->input('option_descriptions', []) as $i => $item) {
            $rules["option_descriptions.$i.name"] =
                (($item['language_code'] ?? '') === $defaultLang)
                    ? 'required|string|max:128'
                    : 'nullable|string|max:128';
        }

        foreach ((array) $this->input('option_values', []) as $i => $value) {
            foreach ((array) ($value['option_value_descriptions'] ?? []) as $j => $desc) {
                $rules["option_values.$i.option_value_descriptions.$j.name"] =
                    (($desc['language_code'] ?? '') === $defaultLang)
                        ? 'required|string|max:128'
                        : 'nullable|string|max:128';
            }
        }

        return $rules;
    }
}
