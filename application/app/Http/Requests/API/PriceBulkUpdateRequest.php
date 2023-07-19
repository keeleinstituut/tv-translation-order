<?php

namespace App\Http\Requests\API;

use App\Http\Requests\Helpers\NestedFormRequestValidator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: ['data'],
        properties: [
            new OA\Property(
                property: 'data',
                type: 'array',
                items: new OA\Items(
                    required: ['id', 'character_fee', 'word_fee', 'page_fee', 'minute_fee', 'hour_fee', 'minimal_fee'],
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'character_fee', type: 'number', format: 'double'),
                        new OA\Property(property: 'word_fee', type: 'number', format: 'double'),
                        new OA\Property(property: 'page_fee', type: 'number', format: 'double'),
                        new OA\Property(property: 'minute_fee', type: 'number', format: 'double'),
                        new OA\Property(property: 'hour_fee', type: 'number', format: 'double'),
                        new OA\Property(property: 'minimal_fee', type: 'number', format: 'double'),
                    ]
                ),
                minItems: 1
            ),
        ]
    )
)]
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
            },
        ];
    }
}
