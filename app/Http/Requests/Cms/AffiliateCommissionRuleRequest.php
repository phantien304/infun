<?php

namespace App\Http\Requests\Cms;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Rate hoa hồng theo ngành hàng. UNIQUE category_id được ép ở DB; validate
 * thêm ở đây để admin nhận thông báo tử tế thay vì lỗi SQL 500.
 */
class AffiliateCommissionRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $ruleId = $this->route('affiliate_commission_rule')?->id;

        return [
            'category_id' => [
                'required', 'integer', 'exists:category,id',
                Rule::unique('affiliate_commission_rule', 'category_id')->ignore($ruleId),
            ],
            'rate' => 'required|numeric|min:0|max:100',
        ];
    }
}
