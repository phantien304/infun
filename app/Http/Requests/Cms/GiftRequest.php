<?php

namespace App\Http\Requests\Cms;

use App\Enums\GiftPickType;
use App\Enums\GiftTriggerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate chương trình quà tặng — màn MỚI, mt219 không có đối chiếu.
 *
 * Ràng buộc chéo (withValidator) đúng theo semantic bảng `gift`:
 *  - trigger_type=1 (min_subtotal) ⇒ min_subtotal bắt buộc.
 *  - trigger_type=2 (buy_specific_product) ⇒ phải có gift_trigger_products.
 *  - pick_type=2 (pick_up_to_n) ⇒ pick_limit bắt buộc, ≥ 1.
 *  - luôn phải có ít nhất 1 gift_item, nếu không chương trình chẳng tặng gì.
 *
 * KHÔNG nhận `used_count` — cột quota do GiftRepository ghi.
 */
class GiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => 'required|string|max:128',
            'description'  => 'nullable|string',
            'trigger_type' => ['required', Rule::in(array_column(GiftTriggerType::cases(), 'value'))],
            'min_subtotal' => 'nullable|numeric|min:0',
            'pick_type'    => ['required', Rule::in(array_column(GiftPickType::cases(), 'value'))],
            'pick_limit'   => 'nullable|integer|min:1',
            'uses_total'   => 'nullable|integer|min:0',
            'date_start'   => 'nullable|date',
            'date_end'     => 'nullable|date|after_or_equal:date_start',
            'is_active'    => 'required|boolean',
            'sort_order'   => 'nullable|integer|min:0',
            'badge'        => 'nullable|string|max:32',

            'gift_items'                       => 'required|array|min:1',
            'gift_items.*.product_id'          => 'required|integer|exists:product,id',
            'gift_items.*.product_variant_id'  => 'nullable|integer|exists:product_variant,id',
            'gift_items.*.quantity'            => 'required|integer|min:1',
            'gift_items.*.sort_order'          => 'nullable|integer|min:0',

            'gift_trigger_products'      => 'array',
            'gift_trigger_products.*.id' => 'required|integer|exists:product,id',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $trigger = (int) $this->input('trigger_type');
            $pick    = (int) $this->input('pick_type');

            if ($trigger === GiftTriggerType::MinSubtotal->value
                && $this->input('min_subtotal') === null) {
                $validator->errors()->add('min_subtotal', trans('validation.required', [
                    'attribute' => 'min_subtotal',
                ]));
            }

            if ($trigger === GiftTriggerType::BuySpecificProduct->value
                && empty($this->input('gift_trigger_products'))) {
                $validator->errors()->add('gift_trigger_products', trans('validation.required', [
                    'attribute' => 'gift_trigger_products',
                ]));
            }

            if ($pick === GiftPickType::PickUpToN->value && $this->input('pick_limit') === null) {
                $validator->errors()->add('pick_limit', trans('validation.required', [
                    'attribute' => 'pick_limit',
                ]));
            }
        });
    }
}
