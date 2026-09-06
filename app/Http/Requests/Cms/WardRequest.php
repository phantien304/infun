<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class WardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $defaultLang = getConfigDb('config_language') ?: 'vi';

        $rules = [
            'district_id' => 'required|numeric',
            'ghn_id'      => 'nullable|string|max:255',
            'vtp_id'      => 'nullable|numeric',
            'name_vtp'    => 'nullable|string|max:255',
            'name_ghn'    => 'nullable|string|max:255',
            'note'        => 'nullable|string',
            'ward_descriptions'                 => 'array',
            'ward_descriptions.*.language_code' => 'required|string|max:11',
        ];

        foreach ((array) $this->input('ward_descriptions', []) as $i => $item) {
            $rules["ward_descriptions.$i.name"] =
                (($item['language_code'] ?? '') === $defaultLang)
                    ? 'required|string|max:255'
                    : 'nullable|string|max:255';
        }

        return $rules;
    }
}
