<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $defaultLang = getConfigDb('config_language') ?: 'vi';

        $rules = [
            'model' => 'required|string|max:255',
            'sku'              => 'nullable|string|max:255',
            'upc'              => 'nullable|string|max:255',
            'ean'              => 'nullable|string|max:255',
            'jan'              => 'nullable|string|max:255',
            'isbn'             => 'nullable|string|max:255',
            'mpn'              => 'nullable|string|max:255',
            'location'         => 'nullable|string|max:255',
            'image'            => 'nullable|string',
            'badge'            => 'nullable|string|max:255',
            'date_available'   => 'nullable|string',
            'link_sale'        => 'nullable|string',
            'link_sale_custom' => 'nullable',
            'manufacturer_id'  => 'nullable|integer',
            'tax_class_id'     => 'nullable|integer',
            'stock_status_id'  => 'nullable|integer',
            'shipping'         => 'nullable',
            'is_add_cart'      => 'nullable',
            'is_custom'        => 'nullable',
            'is_review'        => 'nullable',
            'length'           => 'nullable',
            'width'            => 'nullable',
            'height'           => 'nullable',
            'length_class_id'  => 'nullable|integer',
            'weight'           => 'nullable',
            'weight_class_id'  => 'nullable|integer',
            'points'           => 'nullable',
            'sort_order'       => 'nullable',

            'product_descriptions'                  => 'array',
            'product_descriptions.*.language_code'  => 'required|string|max:11',
            'product_descriptions.*.description'      => 'nullable|string',
            'product_descriptions.*.content'           => 'nullable|string',
            'product_descriptions.*.tag'                => 'nullable|string',
            'product_descriptions.*.meta_title'         => 'nullable|string|max:255',
            'product_descriptions.*.meta_description'   => 'nullable|string',
            'product_descriptions.*.meta_keyword'       => 'nullable|string',

            'product_categories'  => 'array',
            'product_filters'     => 'array',
            'product_related'     => 'array',
            'product_ingredients' => 'array',
            'product_attributes'  => 'array',
            'product_images'      => 'array',
            'product_rewards'     => 'array',
            'product_options'     => 'array',
            'product_variants'    => 'array',
            'product_variants.*.option_value_ids' => 'array',
            'product_variants.*.id'                  => 'nullable',
            'product_variants.*.price'               => 'nullable',
            'product_variants.*.regular_price'       => 'nullable',
            'product_variants.*.sku'                 => 'nullable|string|max:255',
            'product_variants.*.minimum'             => 'nullable',
            'product_variants.*.sort_order'          => 'nullable',
            'product_variants.*.is_default'          => 'nullable',
            'product_variants.*.on_hand'             => 'nullable',
            'product_variants.*.inventory_policy'    => 'nullable',
            'product_variants.*.stocks'                        => 'nullable|array',
            'product_variants.*.stocks.*.warehouse_id'         => 'nullable|integer',
            'product_variants.*.stocks.*.on_hand'              => 'nullable',
            'product_variants.*.stocks.*.inventory_policy'     => 'nullable',
            'product_variants.*.special_price'       => 'nullable',
            'product_variants.*.special_priority'    => 'nullable',
            'product_variants.*.special_date_start'  => 'nullable|string',
            'product_variants.*.special_date_end'    => 'nullable|string',
            'product_variants.*.discounts'                     => 'nullable|array',
            'product_variants.*.discounts.*.id'                => 'nullable',
            'product_variants.*.discounts.*.user_group_id'     => 'nullable|integer',
            'product_variants.*.discounts.*.quantity'          => 'nullable|integer|min:1',
            'product_variants.*.discounts.*.priority'          => 'nullable|integer',
            'product_variants.*.discounts.*.price'             => 'nullable',
            'product_variants.*.discounts.*.date_start'        => 'nullable|string',
            'product_variants.*.discounts.*.date_end'          => 'nullable|string',
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
