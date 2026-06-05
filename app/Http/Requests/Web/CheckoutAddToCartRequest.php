<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutAddToCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id' => 'required|integer|min:1',
            'quantity'   => 'nullable|integer|min:1',
            'option'     => 'nullable|array',
        ];
    }

    /**
     * Validate option payload — chỉ check required + format (email/phone) cho
     * custom field. Variant role không validate ở đây — CartService::add sẽ từ
     * chối combo không match variant (return product_variant_id = null cho
     * product có variants → cart hiển thị giá sai). Caller controller có thể
     * check thêm hasVariants ⇒ phải có variant_id resolve.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $options = (array) $this->input('option', []);
            foreach ($options as $key => $opt) {
                if (! (int) ($opt['required'] ?? 0)) {
                    continue;
                }
                $type = $opt['type'] ?? '';
                $value = $opt['value'] ?? null;
                // Blade `_option.blade.php` emit `option_value_id` cho variant
                // role, `product_option_value_id` cho custom field role
                // (xem $valueParam = $isVariant ? ...). Validator phải accept
                // CẢ HAI key — variant role required mà chỉ check
                // product_option_value_id sẽ luôn fail dù user đã pick.
                $hasValueId = ! empty($opt['option_value_id'])
                           || ! empty($opt['product_option_value_id']);

                if (in_array($type, ['text', 'textarea', 'email', 'phone'], true) && empty($value)) {
                    $v->errors()->add("option.$key.parent", sprintf(trans('messages.TextRequiredInput'), $opt['name'] ?? ''));
                    continue;
                }
                if (in_array($type, ['file', 'datetime', 'date', 'time'], true) && empty($value)) {
                    $v->errors()->add("option.$key.parent", sprintf(trans('messages.TextRequiredChoose'), $opt['name'] ?? ''));
                    continue;
                }
                if (in_array($type, ['image', 'select', 'radio', 'checkbox'], true) && ! $hasValueId) {
                    $v->errors()->add("option.$key.parent", sprintf(trans('messages.TextRequiredChoose'), $opt['name'] ?? ''));
                    continue;
                }
                if ($type === 'email' && filled($value) && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $v->errors()->add("option.$key.parent", trans('messages.ErrorEmail'));
                }
                if ($type === 'phone' && filled($value) && (strlen($value) < 8 || strlen($value) > 12)) {
                    $v->errors()->add("option.$key.parent", trans('messages.ErrorPhone'));
                }
            }
        });
    }
}
