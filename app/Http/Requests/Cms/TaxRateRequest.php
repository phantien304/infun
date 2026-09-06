<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class TaxRateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'                        => 'required|string|max:32',
            'rate'                        => 'required|numeric',
            'type'                        => 'required|string|in:P,F',
            'geo_zone_id'                 => 'required|integer',
            'tax_rate_to_user_groups'     => 'required|array|min:1',
            'tax_rate_to_user_groups.*'   => 'integer',
        ];
    }
}
