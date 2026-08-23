<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CarrierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // orders.carrier_code tham chiếu Carrier theo `code` (KHÔNG phải id —
        // xem Orders::carrier() belongsTo(Carrier::class, 'carrier_code', 'code'))
        // nên code PHẢI duy nhất dù bảng carrier chỉ có PK composite (id, code)
        // chứ không có unique riêng cho code.
        $carrierId = $this->route('carrier')?->id;

        return [
            'code'       => ['required', 'string', 'max:128', Rule::unique('carrier', 'code')->ignore($carrierId)],
            'name'       => 'required|string|max:255',
            'image'      => 'nullable|string|max:255',
            'sort_order' => 'nullable|numeric',
        ];
    }
}
