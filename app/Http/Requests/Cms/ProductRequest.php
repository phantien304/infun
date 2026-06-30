<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate store/update Product (CMS). Cố ý LENIENT vì product nhiều field
 * legacy hay rỗng — chỉ chặn cái cốt lõi (model, name ngôn ngữ mặc định).
 */
class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // quyền đã chặn ở middleware cms.permission
    }

    public function rules(): array
    {
        $defaultLang = getConfigDb('config_language') ?: 'vi';

        $rules = [
            'model' => 'required|string|max:255',

            'product_descriptions'                  => 'array',
            'product_descriptions.*.language_code'  => 'required|string|max:11',

            'product_categories'  => 'array',
            'product_filters'     => 'array',
            'product_related'     => 'array',
            'product_ingredients' => 'array',
            'product_attributes'  => 'array',
            'product_images'      => 'array',
            'product_discounts'   => 'array',
            'product_rewards'     => 'array',
            'product_options'     => 'array',
            'product_variants'    => 'array',
            'product_variants.*.option_value_ids' => 'array',
        ];

        foreach ((array) $this->input('product_descriptions', []) as $i => $item) {
            $rules["product_descriptions.$i.name"] =
                (($item['language_code'] ?? '') === $defaultLang)
                    ? 'required|string|max:255'
                    : 'nullable|string|max:255';
        }

        return $rules;
    }
}
