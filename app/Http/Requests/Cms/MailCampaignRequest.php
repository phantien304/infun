<?php

namespace App\Http\Requests\Cms;

use App\Enums\MailCampaignSendTo;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validate chiến dịch mail marketing.
 *
 * Khác mt219 (`UserValidator::validateSendMail`): ở đó chỉ có
 * `send_to|subject|message` required, còn `user_group` / `users` / `file` thì
 * validate rời trong `_buildValidateRoleBySendMail()`. Ở đây gom về một chỗ
 * bằng `required_if` — nhìn một hàm là biết mỗi tập người nhận cần gì.
 *
 * `file` giới hạn 5MB và mimes txt/csv: file danh sách email là văn bản
 * thuần, nhận .xlsx hay .zip ở đây chỉ dẫn đến parse rác.
 */
class MailCampaignRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => 'required|string|min:3|max:255',
            'message' => 'required|string',
            'send_to' => ['required', Rule::in(array_column(MailCampaignSendTo::cases(), 'value'))],

            'user_group_id' => [
                'nullable',
                Rule::requiredIf(fn () => $this->input('send_to') === MailCampaignSendTo::UserGroup->value),
                'integer',
                'exists:user_group,id',
            ],

            'user_ids' => [
                'nullable',
                Rule::requiredIf(fn () => $this->input('send_to') === MailCampaignSendTo::Users->value),
                'array',
                'min:1',
            ],
            'user_ids.*' => 'integer|exists:user,id',

            'file' => [
                'nullable',
                Rule::requiredIf(fn () => $this->input('send_to') === MailCampaignSendTo::File->value),
                'file',
                'mimes:txt,csv',
                'max:5120',
            ],
        ];
    }
}
