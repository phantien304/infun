<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class CountryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'              => 'required|string|max:128',
            'iso_code_2'        => 'nullable|string|size:2',
            'iso_code_3'        => 'nullable|string|size:3',
            'address_format'    => 'nullable|string',
            'postcode_required' => 'nullable|boolean',
            'status'            => 'nullable|boolean',
        ];
    }
}
