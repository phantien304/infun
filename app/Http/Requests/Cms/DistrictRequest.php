<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DistrictRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $defaultLang = getConfigDb('config_language') ?: 'vi';
        $districtId = $this->route('district')?->id;

        $rules = [
            'zone_id'      => 'required|numeric',
            'code'         => 'nullable|string|max:255',
            'ghn_id'       => ['nullable', 'numeric', Rule::unique('district', 'ghn_id')->ignore($districtId)],
            'name_ghn'     => 'nullable|string|max:255',
            'vtp_id'       => ['nullable', 'numeric', Rule::unique('district', 'vtp_id')->ignore($districtId)],
            'vtp_value'    => 'nullable|string|max:32',
            'type'         => 'nullable|numeric',
            'support_type' => 'nullable|numeric',
            'district_descriptions'                 => 'array',
            'district_descriptions.*.language_code' => 'required|string|max:11',
        ];

        foreach ((array) $this->input('district_descriptions', []) as $i => $item) {
            $rules["district_descriptions.$i.name"] =
                (($item['language_code'] ?? '') === $defaultLang)
                    ? 'required|string|max:255'
                    : 'nullable|string|max:255';
        }

        return $rules;
    }
}
