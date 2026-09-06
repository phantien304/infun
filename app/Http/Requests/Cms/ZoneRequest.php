<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ZoneRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $defaultLang = getConfigDb('config_language') ?: 'vi';
        $zoneId = $this->route('zone')?->id;

        $rules = [
            'code'       => 'nullable|string|max:32',
            'ghn_id'     => ['nullable', 'numeric', Rule::unique('zone', 'ghn_id')->ignore($zoneId)],
            'ghn_code'   => ['nullable', 'numeric', Rule::unique('zone', 'ghn_code')->ignore($zoneId)],
            'vtp_id'     => ['nullable', 'numeric', Rule::unique('zone', 'vtp_id')->ignore($zoneId)],
            'vtp_code'   => ['nullable', 'string', 'max:32', Rule::unique('zone', 'vtp_code')->ignore($zoneId)],
            'sort_order' => 'nullable|numeric',
            'status'     => 'nullable|boolean',
            'zone_descriptions'                    => 'array',
            'zone_descriptions.*.language_code'    => 'required|string|max:11',
        ];

        foreach ((array) $this->input('zone_descriptions', []) as $i => $item) {
            $rules["zone_descriptions.$i.name"] =
                (($item['language_code'] ?? '') === $defaultLang)
                    ? 'required|string|max:255'
                    : 'nullable|string|max:255';
        }

        return $rules;
    }
}
