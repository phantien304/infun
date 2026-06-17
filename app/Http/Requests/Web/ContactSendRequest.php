<?php

namespace App\Http\Requests\Web;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * Validate form liên hệ (POST /lien-he/send). Thay validator legacy
 * `ContactValidator::validateCreate`.
 *
 * Field khớp form blade: name / email / phone / service / content. `content`
 * optional (textarea không `required` ở blade).
 */
class ContactSendRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'    => 'required|string|max:255',
            'email'   => 'required|email|max:255',
            'phone'   => 'required|string|max:30',
            'service' => 'required|string|max:255',
            'content' => 'nullable|string|max:5000',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required'    => 'Vui lòng nhập họ tên',
            'name.max'         => 'Họ tên quá dài',
            'email.required'   => 'Vui lòng nhập email',
            'email.email'      => 'Email không hợp lệ',
            'phone.required'   => 'Vui lòng nhập số điện thoại',
            'service.required' => 'Vui lòng chọn dịch vụ',
        ];
    }

    /**
     * Form liên hệ submit qua AJAX; JS client đọc shape
     * `{success:false, message:{field:[...]}}` với HTTP 200 (jQuery `.done`
     * chỉ fire trên 2xx). Override để giữ contract legacy thay vì shape mặc
     * định 422 của Laravel (`{message, errors}`).
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            errValidator($validator->errors()->messages(), 200)
        );
    }
}
