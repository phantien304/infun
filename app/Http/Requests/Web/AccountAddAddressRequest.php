<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Endpoint `POST /account/add-address` — visitor (chưa login) chọn địa chỉ
 * mặc định lưu vào cookie, hoặc user đã login set 1 trong các địa chỉ là
 * mặc định. Validate input có đủ để build full_address.
 */
class AccountAddAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id'            => 'nullable|integer',
            'address'       => 'required|string|max:500',
            'ward_id'       => 'required|integer',
            'ward_name'     => 'required|string|max:255',
            'district_id'   => 'required|integer',
            'district_name' => 'required|string|max:255',
            'zone_id'       => 'required|integer',
            'zone_name'     => 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'address.required'       => trans('messages.ErrorAddress'),
            'zone_id.required'       => trans('messages.ErrorZone'),
            'district_id.required'   => trans('messages.ErrorDistrict'),
            'ward_id.required'       => trans('messages.ErrorWard'),
        ];
    }
}
