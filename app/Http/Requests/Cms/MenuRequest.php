<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MenuRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'    => 'required|string|max:255',
            'position' => ['required', Rule::in(['top', 'footer'])],
            'theme'    => ['nullable', 'string', Rule::in((array) config('theme.available', []))],
        ];
    }
}
