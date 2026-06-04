<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Thay validator legacy `OrderValidator::validateCancelOrder`. Logic
 * chỉ kiểm tra format input — quyền huỷ (status có nằm trong allowed set
 * hay không, có còn trong window thời gian không) là việc của
 * AccountService, không phải FormRequest.
 */
class AccountCancelOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'order_id'      => 'required|integer|min:1',
            'return_reason' => 'required|string|max:255',
            'comment'       => 'nullable|string|max:1000',
        ];
    }

    public function messages(): array
    {
        return [
            'order_id.required'      => trans('messages.ErrorAction'),
            'return_reason.required' => trans('messages.ErrorReturnReason'),
        ];
    }
}
