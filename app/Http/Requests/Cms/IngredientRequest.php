<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class IngredientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'           => 'required|string|max:255',
            'description'    => 'required|string',
            'warning'        => 'required|in:0,1',
            'warning_text'   => 'nullable|string',
            'effect_ids'     => 'nullable|array',
            'effect_ids.*'   => 'integer',
            'safety_ids'     => 'nullable|array',
            'safety_ids.*'   => 'integer',
            'skincare_ids'   => 'nullable|array',
            'skincare_ids.*' => 'integer',
        ];
    }
}
