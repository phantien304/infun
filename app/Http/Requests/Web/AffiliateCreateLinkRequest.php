<?php

namespace App\Http\Requests\Web;

use Illuminate\Foundation\Http\FormRequest;

/**
 * `POST /account/affiliate/links` — KOL tạo short link (Phase 4).
 * Validate cùng-domain / chặn loop nằm ở AffiliatePortalService
 * (normalizeDestination) vì cần logic parse_url; đây chỉ chặn input thô.
 */
class AffiliateCreateLinkRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'url'    => 'required|string|max:512',
            'sub_id' => 'nullable|string|max:64|regex:/^[A-Za-z0-9_\-]+$/',
        ];
    }

    public function messages(): array
    {
        return [
            'url.required' => trans('messages.affiliate.error_url'),
            'url.max'      => trans('messages.affiliate.error_url'),
            'sub_id.regex' => trans('messages.affiliate.error_sub_id'),
        ];
    }
}
