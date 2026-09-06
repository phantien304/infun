<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class StockStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $defaultLang = getConfigDb('config_language') ?: 'vi';

        $rules = [
            'stock_status_descriptions'                 => 'array',
            'stock_status_descriptions.*.language_code' => 'required|string|max:11',
        ];

        foreach ((array) $this->input('stock_status_descriptions', []) as $i => $item) {
            $rules["stock_status_descriptions.$i.name"] =
                (($item['language_code'] ?? '') === $defaultLang)
                    ? 'required|string|max:32'
                    : 'nullable|string|max:32';
        }

        return $rules;
    }
}
