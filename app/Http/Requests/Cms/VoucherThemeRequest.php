<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate voucher theme. Tên bắt buộc ở ngôn ngữ mặc định, các ngôn ngữ
 * khác nullable — cùng luật với mt219 `VoucherThemeValidator`, viết lại theo
 * FormRequest (mirror ReviewTagRequest).
 */
class VoucherThemeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $defaultLang = getConfigDb('config_language') ?: 'vi';

        $rules = [
            'image' => 'required|string|max:255',

            'voucher_theme_descriptions'                 => 'array',
            'voucher_theme_descriptions.*.language_code' => 'required|string|max:11',
        ];

        foreach ((array) $this->input('voucher_theme_descriptions', []) as $i => $item) {
            $rules["voucher_theme_descriptions.$i.name"] =
                (($item['language_code'] ?? '') === $defaultLang)
                    ? 'required|string|max:32'
                    : 'nullable|string|max:32';
        }

        return $rules;
    }
}
