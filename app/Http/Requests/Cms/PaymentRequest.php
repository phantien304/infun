<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $paymentId = $this->route('payment')?->id;

        return [
            'code'       => ['required', 'string', 'max:32', Rule::unique('payment', 'code')->ignore($paymentId)],
            'name'       => 'required|string|max:255',
            'image'      => 'nullable|string|max:255',
            'sort_order' => 'nullable|numeric',
        ];
    }
}
