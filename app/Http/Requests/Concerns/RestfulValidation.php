<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

trait RestfulValidation
{
    protected function failedValidation(Validator $validator): void
    {
        if (! $this->expectsJson() && ! $this->ajax()) {
            parent::failedValidation($validator);
            return;
        }

        throw new HttpResponseException(
            respondError(trans('messages.ErrorValidation'), 422, $validator->errors()->toArray())
        );
    }
}
