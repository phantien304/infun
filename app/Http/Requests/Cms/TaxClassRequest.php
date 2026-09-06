<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class TaxClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'                    => 'required|string|max:32',
            'description'              => 'nullable|string|max:255',
            'tax_rules'                => 'nullable|array',
            'tax_rules.*.tax_rate_id'  => 'required|integer',
            'tax_rules.*.based'        => 'required|string|in:shipping,payment,store',
            'tax_rules.*.priority'     => 'nullable|integer',
        ];
    }
}
