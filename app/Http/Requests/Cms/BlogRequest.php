<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validate cho store + update Blog (REST).
 * Title bắt buộc cho ngôn ngữ mặc định, các ngôn ngữ khác không bắt buộc.
 * FormRequest tự trả 422 + { message, errors:{ field:[...] } } (chuẩn Laravel).
 */
class BlogRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Quyền đã chặn ở middleware (auth:sanctum + cms.permission). Cho qua ở tầng request.
        return true;
    }

    public function rules(): array
    {
        $defaultLang = getConfigDb('config_language') ?: 'vi';

        $rules = [
            'category_id' => 'nullable|numeric',
            'author_id'   => 'nullable|numeric',
            'image'       => 'nullable|string|max:255',
            'viewed'      => 'nullable|numeric',
            'featured'    => 'nullable|numeric',
            'blog_descriptions'                    => 'array',
            'blog_descriptions.*.language_code'    => 'required|string|max:11',
            'blog_descriptions.*.description'      => 'nullable|string',
            'blog_descriptions.*.content'          => 'nullable|string',
            'blog_descriptions.*.tag'              => 'nullable|string',
            'blog_descriptions.*.meta_title'       => 'nullable|string|max:255',
            'blog_descriptions.*.meta_description' => 'nullable|string|max:255',
        ];

        foreach ((array) $this->input('blog_descriptions', []) as $i => $item) {
            $rules["blog_descriptions.$i.title"] =
                (($item['language_code'] ?? '') === $defaultLang)
                    ? 'required|string|max:255'
                    : 'nullable|string|max:255';
        }

        return $rules;
    }
}
