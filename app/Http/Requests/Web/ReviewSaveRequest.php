<?php

namespace App\Http\Requests\Web;

use App\Models\Entities\ReviewCriteria;
use Illuminate\Foundation\Http\FormRequest;

class ReviewSaveRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'product_id'         => 'required|integer|exists:product,id',
            'product_variant_id' => 'nullable|integer|exists:product_variant,id',
            'order_id'           => 'nullable|integer|exists:orders,id',
            'title'              => 'nullable|string|max:255',
            'text'               => 'required|string|min:5|max:2000',
            'is_anonymous'       => 'nullable|boolean',
            'tags'               => 'nullable|array|max:8',
            'tags.*'             => 'string|max:64',
            'media'              => 'nullable|array|max:10',
            'media.*'            => 'file|max:30720|mimetypes:image/jpeg,image/png,image/webp,image/gif,video/mp4,video/quicktime,video/webm',
        ];

        $rating = $this->input('rating');
        if (is_array($rating)) {
            $criteriaList = ReviewCriteria::active()->get();
            foreach ($criteriaList as $c) {
                $key = "rating.{$c->code}";
                $rules[$key] = ($c->is_required ? 'required' : 'nullable') . '|integer|between:1,5';
            }
        } else {
            $rules['rating'] = 'required|integer|between:1,5';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'product_id.required' => trans('messages.review.save.product_required'),
            'product_id.exists'   => trans('messages.review.save.product_not_found'),
            'text.required'       => trans('messages.review.save.text_required'),
            'text.min'            => trans('messages.review.save.text_min'),
            'text.max'            => trans('messages.review.save.text_max'),
            'rating.required'     => trans('messages.review.save.rating_required'),
            'rating.between'      => trans('messages.review.save.rating_between'),
            'media.max'           => trans('messages.review.save.media_max'),
            'media.*.max'         => trans('messages.review.save.media_size_max'),
            'media.*.mimetypes'   => trans('messages.review.save.media_mimetypes'),
        ];
    }
}
