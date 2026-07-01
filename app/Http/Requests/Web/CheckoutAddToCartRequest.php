<?php

namespace App\Http\Requests\Web;

use App\Http\Requests\Concerns\RestfulValidation;
use Illuminate\Foundation\Http\FormRequest;

class CheckoutAddToCartRequest extends FormRequest
{
    use RestfulValidation;

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
}
