<?php

namespace App\Http\Requests\Web;

use App\Http\Requests\Concerns\RestfulValidation;
use Illuminate\Foundation\Http\FormRequest;

class ReviewVoteRequest extends FormRequest
{
    use RestfulValidation;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'review_id' => 'required|integer|exists:review,id',
            'vote_type' => 'required|integer|in:-1,0,1',
        ];
    }

    public function messages(): array
    {
        return [
            'review_id.required' => trans('messages.review.review_id_required'),
            'review_id.exists'   => trans('messages.review.review_not_found'),
            'vote_type.in'       => trans('messages.review.vote_invalid'),
        ];
    }
}
