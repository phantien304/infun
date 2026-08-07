<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate store/update Order (CMS). Dùng CHUNG cho 2 trang FE khác nhau:
 *   - order/form.jsx (wizard 3 tab: tạo mới / sửa đầy đủ) → LUÔN gửi kèm
 *     `products` (thay toàn bộ dòng sản phẩm) + payment_code/carrier_code.
 *   - order/view.jsx (đổi trạng thái + sửa thông tin khách, KHÔNG đụng sản
 *     phẩm/vận chuyển/thanh toán) → KHÔNG gửi `products`/payment_code/carrier_code.
 * `required_with:products` là cách phân biệt 2 luồng đó ở tầng validate.
 *
 * QUAN TRỌNG (đã học từ ProductRequest — xem comment ở đó): FormRequest::validated()
 * chỉ giữ field có khai rule (kể cả 'nullable'); field mảng con thiếu rule sẽ
 * bị lặng lẽ xoá dù request gửi đúng dữ liệu. Vì vậy khai đủ rule cho MỌI
 * field, kể cả field không bắt buộc.
 */
class OrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // quyền đã chặn ở middleware cms.permission
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
            // Chỉ FE gửi kèm cho dòng ĐÃ tồn tại (nạp từ GET /order/{id}) —
            // lưới an toàn khi option_value_ids rỗng, xem
            // OrderAdminWriteService::resolveLine() + form.jsx productsFromDetail().
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
