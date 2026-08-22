<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class OrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $defaultLang = getConfigDb('config_language_admin') ?: 'vi';

        $rules = [
            'descriptions'                  => 'required|array|min:1',
            'descriptions.*.language_code'  => 'required|string|max:11',
            'descriptions.*.name'           => 'nullable|string|max:255',
        ];

        foreach ((array) $this->input('descriptions', []) as $i => $item) {
            $rules["descriptions.$i.name"] = (($item['language_code'] ?? '') === $defaultLang)
                ? 'required|string|max:255'
                : 'nullable|string|max:255';
        }

        return $rules;
    }
}
