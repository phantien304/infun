<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate cho store + update Warehouse (REST). Warehouse KHÔNG có bảng
 * dịch (chỉ code/name/address/... phẳng — giống Menu, khác Category/Blog).
 */
class WarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Quyền đã chặn ở middleware (auth:sanctum + cms.permission). Cho qua ở tầng request.
        return true;
    }

    public function rules(): array
    {
        // Route param 'warehouse' (Route::apiResource('warehouse', ...)) —
        // model binding, null lúc store.
        $warehouse = $this->route('warehouse');

        return [
            'code'         => [
                'required', 'string', 'max:32',
                Rule::unique('warehouse', 'code')->ignore($warehouse?->id),
            ],
            'name'         => 'required|string|max:255',
            'address'      => 'nullable|string|max:255',
            'zone_id'      => 'nullable|integer',
            'district_id'  => 'nullable|integer',
            'ward_id'      => 'nullable|integer',
            'telephone'    => 'nullable|string|max:32',
            'priority'     => 'nullable|integer|min:0',
            'is_active'    => 'nullable|boolean',
            'is_sellable'  => 'nullable|boolean',
        ];
    }
}
