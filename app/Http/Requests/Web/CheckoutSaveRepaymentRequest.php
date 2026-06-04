<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutSaveRepaymentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'order_id'     => 'required|integer|exists:orders,id',
            'payment_code' => 'required|string|max:50',
        ];
    }
}
