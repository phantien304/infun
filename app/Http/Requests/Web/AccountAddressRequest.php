<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Thay validator legacy `UserAddressValidator::validateAddress`. Dùng
 * cho cả tạo mới + cập nhật địa chỉ — `id` optional, repo decide upsert.
 */
class AccountAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'id'           => 'nullable|integer',
            'full_name'    => 'required|string|max:255',
            'telephone'    => 'required|string|min:8|max:15',
            'zone_id'      => 'required|integer',
            'district_id'  => 'required|integer',
            'ward_id'      => 'required|integer',
            'address'      => 'required|string|max:500',
            'is_default'   => 'nullable',
            'redirect_url' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'full_name.required'    => trans('messages.ErrorFullName'),
            'telephone.required'    => trans('messages.ErrorPhone'),
            'telephone.min'         => trans('messages.ErrorPhone'),
            'telephone.max'         => trans('messages.ErrorPhone'),
            'zone_id.required'      => trans('messages.ErrorZone'),
            'district_id.required' => trans('messages.ErrorDistrict'),
            'ward_id.required'      => trans('messages.ErrorWard'),
            'address.required'      => trans('messages.ErrorAddress'),
        ];
    }
}
