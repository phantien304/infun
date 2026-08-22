<?php

namespace App\Http\Requests\Cms;

use App\Enums\CouponApplyScope;
use App\Enums\CouponType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate coupon từ CMS.
 *
 * Khác mt219 (`CouponValidator`): `type` không còn CHAR 'P'/'F' mà là enum
 * số CouponType (1=percent, 2=fixed, 3=freeship). Rule sinh từ enum để thêm
 * loại mới không phải sửa 2 chỗ.
 *
 * KHÔNG nhận `used_count` — cột denormalize quota, chỉ repository ghi.
 */
class CouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $couponId = $this->route('coupon')?->id;

        return [
            'name'        => 'required|string|max:128',
            'description' => 'nullable|string',
            'code'        => [
                'required', 'string', 'max:20',
                Rule::unique('coupon', 'code')->ignore($couponId),
            ],
            'type' => ['required', Rule::in(array_column(CouponType::cases(), 'value'))],

            // Percent: discount là % nên trần 100. Freeship: bỏ qua discount
            // (CouponService ép shipping_fee = 0) nhưng vẫn phải là số hợp lệ.
            'discount' => [
                'required', 'numeric', 'min:0',
                Rule::when(
                    (int) $this->input('type') === CouponType::Percent->value,
                    ['max:100'],
                ),
            ],
            'discount_max' => 'nullable|numeric|min:0',

            'total'        => 'nullable|numeric|min:0',
            'min_subtotal' => 'nullable|numeric|min:0',

            'apply_scope'   => ['required', Rule::in(array_column(CouponApplyScope::cases(), 'value'))],
            'user_group_id' => 'nullable|integer|exists:user_group,id',

            'logged'   => 'nullable|boolean',
            'shipping' => 'nullable|boolean',

            'date_start' => 'nullable|date',
            'date_end'   => 'nullable|date|after_or_equal:date_start',

            'uses_total'    => 'nullable|integer|min:0',
            'uses_customer' => 'nullable|integer|min:0',

            'is_active'  => 'required|boolean',
            'sort_order' => 'nullable|integer|min:0',
            'badge'      => 'nullable|string|max:32',

            'coupon_products'      => 'array',
            'coupon_products.*.id' => 'required|integer|exists:product,id',

            'coupon_categories'      => 'array',
            'coupon_categories.*.id' => 'required|integer|exists:category,id',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $scope = (int) $this->input('apply_scope');

            if ($scope === CouponApplyScope::Products->value && empty($this->input('coupon_products'))) {
                $validator->errors()->add('coupon_products', trans('validation.required', [
                    'attribute' => 'coupon_products',
                ]));
            }

            if ($scope === CouponApplyScope::Categories->value && empty($this->input('coupon_categories'))) {
                $validator->errors()->add('coupon_categories', trans('validation.required', [
                    'attribute' => 'coupon_categories',
                ]));
            }
        });
    }
}
