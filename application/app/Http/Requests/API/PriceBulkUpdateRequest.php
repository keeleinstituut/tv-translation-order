<?php

namespace App\Http\Requests\API;

use App\Http\Requests\Helpers\NestedFormRequestValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PriceBulkUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'data' => 'required|array|min:1',
        ];
    }

    public function after()
    {
        return [
            function (Validator $validator) {
                collect($this->data)->each(function ($element, $index) use ($validator) {
                    NestedFormRequestValidator::formRequest(new PriceUpdateRequest())
                        ->setData($element)
                        ->validate()
                        ->setMessagesToValidator($validator, "data.$index");
                });
            }
        ];
    }
}
