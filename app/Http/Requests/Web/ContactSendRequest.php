<?php

namespace App\Http\Requests\Web;

use App\Http\Requests\Concerns\RestfulValidation;
use Illuminate\Foundation\Http\FormRequest;

class ContactSendRequest extends FormRequest
{
    use RestfulValidation;

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
            'name.required'    => trans('messages.contact.name_required'),
            'name.max'         => trans('messages.contact.name_max'),
            'email.required'   => trans('messages.contact.email_required'),
            'email.email'      => trans('messages.contact.email_invalid'),
            'phone.required'   => trans('messages.contact.phone_required'),
            'service.required' => trans('messages.contact.service_required'),
        ];
    }
}
