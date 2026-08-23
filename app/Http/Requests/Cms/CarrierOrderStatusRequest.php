<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class CarrierOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'carrier_id'  => 'required|integer|exists:carrier,id',
            'code'        => 'required|string|max:128',
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
        ];
    }
}
