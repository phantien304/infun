<?php

namespace App\Http\Requests\Web;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

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
     * Validate option payload — required + format (email/phone) cho custom
     * field; variant role thì always-required (semantically: product có variant
     * mà không chọn variant = không xác định SKU, cart sẽ rơi vào nhánh
     * `product_variant_id = null` → giá sai). Không tin tưởng cờ `required`
     * trong DB cho variant — admin có thể set 0 do quên.
     *
     * Errors gắn key `option.{id}.parent` để style.js render đúng vị trí
     * (`$('#option-' + id).after(...)`).
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($v) {
            $options = (array) $this->input('option', []);
            $variantRole = (string) getCoreConfig('option.role_variant');

            foreach ($options as $key => $opt) {
                $role = (string) ($opt['role'] ?? '');
                $isVariant = $role === $variantRole;
                $required = (int) ($opt['required'] ?? 0) === 1 || $isVariant;

                if (! $required) {
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

    /**
     * Reshape validation errors về dạng style.js handler đang đọc:
     *
     *   { success: false, message: { {optId}: {parent: "..."}, quantity: "..." } }
     *
     * Mặc định Laravel trả 422 + `{message, errors: {key: [...]}}` cho AJAX —
     * style.js chỉ define `success` callback (không có `error` callback) →
     * validation fail trở thành silent failure ở client (XHR 422 không bao
     * giờ trigger UI). Trả 200 + shape phẳng cho phép `success` callback
     * render error trực tiếp ngay dưới block variant tương ứng.
     */
    protected function failedValidation(Validator $validator): void
    {
        if (! $this->expectsJson() && ! $this->ajax()) {
            parent::failedValidation($validator);

            return;
        }

        $message = [];
        $top = [];

        foreach ($validator->errors()->messages() as $key => $msgs) {
            $msg = is_array($msgs) ? ($msgs[0] ?? '') : (string) $msgs;

            if (preg_match('/^option\.([^.]+)\.parent$/', $key, $m)) {
                $message[$m[1]]['parent'] = $msg;
                continue;
            }
            if ($key === 'quantity') {
                $message['quantity'] = $msg;
                continue;
            }
            $top[$key] = $msg;
        }

        // Fallback: lỗi không thuộc option/quantity (vd product_id required) →
        // gắn vào `message.global` để client có thể hiển thị toast nếu cần.
        if (empty($message) && ! empty($top)) {
            $message['global'] = reset($top);
        }

        throw new HttpResponseException(
            response()->json([
                'success'   => false,
                'validator' => false,
                'code'      => 200,
                'message'   => $message,
            ], 200)
        );
    }
}
