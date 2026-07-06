<?php

namespace App\Http\Requests\Web;

use App\Enums\OptionRole;
use App\Enums\OptionType;
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

            foreach ($options as $key => $opt) {
                $isVariant = OptionRole::fromInput($opt['role'] ?? null)->isVariant();
                $required = (int) ($opt['required'] ?? 0) === 1 || $isVariant;

                if (! $required) {
                    continue;
                }

                $optType = OptionType::tryFrom((string) ($opt['type'] ?? ''));
                $value = $opt['value'] ?? null;
                $hasValueId = ! empty($opt['option_value_id']);

                if ($optType?->expectsTextInput() && empty($value)) {
                    $v->errors()->add("option.$key.parent", sprintf(trans('messages.TextRequiredInput'), $opt['name'] ?? ''));
                    continue;
                }
                if ($optType?->expectsDateOrFileChoice() && empty($value)) {
                    $v->errors()->add("option.$key.parent", sprintf(trans('messages.TextRequiredChoose'), $opt['name'] ?? ''));
                    continue;
                }
                if ($optType?->isVariantWidget() && ! $hasValueId) {
                    $v->errors()->add("option.$key.parent", sprintf(trans('messages.TextRequiredChoose'), $opt['name'] ?? ''));
                    continue;
                }
                if ($optType === OptionType::Email && filled($value) && ! filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $v->errors()->add("option.$key.parent", trans('messages.ErrorEmail'));
                }
                if ($optType === OptionType::Phone && filled($value) && (strlen($value) < 8 || strlen($value) > 12)) {
                    $v->errors()->add("option.$key.parent", trans('messages.ErrorPhone'));
                }
            }
        });
    }
}
