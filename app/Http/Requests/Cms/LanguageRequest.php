<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class LanguageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $languageId = $this->route('language')?->id;

        return [
            'code'      => ['required', 'string', 'max:50', Rule::unique('language', 'code')->ignore($languageId)],
            'name'      => 'required|string|max:50',
            'vi_name'   => 'nullable|string|max:50',
            'priority'  => 'nullable|numeric',
            'flag_icon' => 'nullable|string|max:500',
        ];
    }
}
