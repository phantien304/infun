<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class EffectRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'       => 'required|string|max:255',
            'icon'       => 'nullable|string|max:255',
            'image_icon' => 'nullable|string|max:255',
            'sort_order' => 'nullable|numeric',
        ];
    }
}
