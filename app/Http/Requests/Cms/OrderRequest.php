<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

class OrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'user_id'         => 'nullable|integer',
            'user_address_id' => 'nullable|integer',
            'full_name'       => 'required|string|max:255',
            'email'           => 'nullable|email|max:255',
            'telephone'       => 'required|string|min:8|max:20',
            'address'         => 'required|string|max:500',
            'zone_id'         => 'required|integer',
            'district_id'     => 'required|integer',
            'ward_id'         => 'required|integer',
            'payment_code'    => 'nullable|required_with:products|string|max:50',
            'carrier_code'    => 'nullable|required_with:products|string|max:50',
            'comment'         => 'nullable|string|max:1000',
            'order_status_id' => 'required|integer',
            'note'            => 'nullable|string|max:1000',
            'send_mail'       => 'nullable|boolean',

            'products'                                  => 'sometimes|array|min:1',
            'products.*.product_id'                     => 'required_with:products|integer',
            'products.*.product_variant_id'             => 'nullable|integer',
            'products.*.quantity'                       => 'required_with:products|integer|min:1',
            'products.*.option_value_ids'                => 'nullable|array',
            'products.*.option_value_ids.*.option_id'    => 'nullable|integer',
            'products.*.option_value_ids.*.value_id'     => 'nullable|integer',
            'products.*.custom_options'                  => 'nullable|array',
            'products.*.custom_options.*.option_id'      => 'nullable|integer',
            'products.*.custom_options.*.value'          => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required'    => trans('messages.ErrorFullName'),
            'telephone.required'    => trans('messages.ErrorPhone'),
            'address.required'      => trans('messages.ErrorAddress'),
            'zone_id.required'      => trans('messages.ErrorZone'),
            'district_id.required' => trans('messages.ErrorDistrict'),
            'ward_id.required'      => trans('messages.ErrorWard'),
            'email.email'           => trans('messages.ErrorEmail'),
        ];
    }
}
