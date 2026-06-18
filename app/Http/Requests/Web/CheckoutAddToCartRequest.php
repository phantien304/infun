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
                // Blade `_option.blade.php` emit giá trị được chọn dưới key
                // `option_value_id` cho MỌI option có picker (radio/image/select/
                // checkbox), không phân biệt role. `product_option_value_id` chỉ
                // thuộc tầng lưu đơn (cột orders_product_option), KHÔNG phải payload.
                $hasValueId = ! empty($opt['option_value_id']);

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
