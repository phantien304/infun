<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class ManufacturerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'             => 'required|string|max:64',
            'image'            => 'nullable|string|max:255',
            'sort_order'       => 'nullable|numeric',
            'meta_title'       => 'nullable|string|max:255',
            'meta_description' => 'nullable|string|max:255',
        ];
    }
}
