<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

class ReviewReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'review_id'   => 'required|integer|exists:review,id',
            'reason_code' => 'required|string|in:spam,offensive,fake,irrelevant,other',
            'description' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'review_id.required'   => trans('messages.review.review_id_required'),
            'review_id.exists'     => trans('messages.review.review_not_found'),
            'reason_code.required' => trans('messages.review.reason_required'),
            'reason_code.in'       => trans('messages.review.reason_invalid'),
            'description.max'      => trans('messages.review.description_max'),
        ];
    }
}
