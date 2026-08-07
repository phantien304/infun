<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate cho store + update StoreReview (REST) — mirror CategoryRequest.
 * Title bắt buộc cho ngôn ngữ mặc định, các ngôn ngữ khác không bắt buộc.
 */
class StoreReviewRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Quyền đã chặn ở middleware (cms.permission, xem routes/rcms.php).
        return true;
    }

    public function rules(): array
    {
        $defaultLang = getConfigDb('config_language') ?: 'vi';

        $rules = [
            'name'        => 'nullable|string|max:255',
            'image'       => 'nullable|string|max:255',
            'social_icon' => 'nullable|string|max:255',
            'featured'    => 'nullable|numeric|in:0,1',
            'viewed'      => 'nullable|numeric',
            'product_id'  => 'nullable|numeric',
            'author_id'   => 'nullable|numeric',
            'store_review_descriptions'                    => 'array',
            'store_review_descriptions.*.language_code'    => 'required|string|max:11',
            'store_review_descriptions.*.content'          => 'nullable|string',
            'store_review_descriptions.*.meta_title'       => 'nullable|string|max:255',
            'store_review_descriptions.*.meta_description' => 'nullable|string|max:255',
        ];

        foreach ((array) $this->input('store_review_descriptions', []) as $i => $item) {
            $rules["store_review_descriptions.$i.title"] =
                (($item['language_code'] ?? '') === $defaultLang)
                    ? 'required|string|max:255'
                    : 'nullable|string|max:255';
        }

        return $rules;
    }
}
