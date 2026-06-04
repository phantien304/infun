<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate input cho save order. Thay cho OrderValidator->validateCreate cũ
 * (class validator legacy không còn tồn tại trong project).
 */
class CheckoutSaveOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'full_name'    => 'required|string|max:255',
            'email'        => 'nullable|email|max:255',
            'telephone'    => 'required|string|min:8|max:15',
            'address'      => 'required|string|max:500',
            'zone_id'      => 'required|integer',
            'district_id'  => 'required|integer',
            'ward_id'      => 'required|integer',
            'payment_code' => 'required|string|max:50',
            'carrier_code' => 'nullable|string|max:50',
            'comment'      => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required'    => trans('messages.ErrorFullName'),
            'telephone.required'    => trans('messages.ErrorPhone'),
            'telephone.min'         => trans('messages.ErrorPhone'),
            'telephone.max'         => trans('messages.ErrorPhone'),
            'address.required'      => trans('messages.ErrorAddress'),
            'zone_id.required'      => trans('messages.ErrorZone'),
            'district_id.required' => trans('messages.ErrorDistrict'),
            'ward_id.required'      => trans('messages.ErrorWard'),
            'payment_code.required' => trans('messages.ErrorPayment'),
            'email.email'           => trans('messages.ErrorEmail'),
        ];
    }
}
