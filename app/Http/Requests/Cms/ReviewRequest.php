<?php

namespace App\Http\Requests\Cms;

use App\Enums\ReviewStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Chỉ dùng cho store() — admin tạo review "mồi" (seed review) cho sản phẩm
 * mới chưa có đánh giá thật. update() dùng validate() riêng trong
 * ReviewController::update() (status required, product_id không cho sửa).
 */
class ReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'product_id'                    => 'required|integer|exists:product,id',
            'author'                        => 'required|string|max:64',
            'title'                         => 'nullable|string|max:255',
            'text'                          => 'required|string|max:5000',
            'rating'                        => 'required|integer|min:1|max:5',
            'status'                        => ['nullable', Rule::in(array_column(ReviewStatus::cases(), 'value'))],
            'is_publish'                    => 'nullable|boolean',
            'is_anonymous'                  => 'nullable|boolean',
            'ratings'                       => 'nullable|array',
            'ratings.*.review_criteria_id'  => 'required_with:ratings|integer|exists:review_criteria,id',
            'ratings.*.rating'              => 'required_with:ratings|integer|min:1|max:5',
            'tag_ids'                       => 'nullable|array',
            'tag_ids.*'                     => 'integer|exists:review_tag,id',
        ];
    }
}
