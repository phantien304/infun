<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CurrencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $currencyId = $this->route('currency')?->id;

        return [
            'code'          => ['required', 'string', 'max:255', Rule::unique('currency', 'code')->ignore($currencyId)],
            'title'         => 'required|string|max:255',
            'unit'          => 'nullable|string|max:255',
            'value'         => 'required|numeric',
            'symbol_left'   => 'nullable|string|max:255',
            'symbol_right'  => 'nullable|string|max:255',
            'decimal_place' => 'nullable|numeric',
            'sort_order'    => 'nullable|numeric',
        ];
    }
}
