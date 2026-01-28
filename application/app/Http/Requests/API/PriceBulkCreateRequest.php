<?php

namespace App\Http\Requests\API;

use App\Http\Requests\Helpers\NestedFormRequestValidator;
use Illuminate\Contracts\Validation\ValidationRule;
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
                    required: [
                        'vendor_id', 'skill_id', 'src_lang_classifier_value_id', 'dst_lang_classifier_value_id',
                        'character_fee', 'word_fee', 'page_fee', 'minute_fee', 'hour_fee',
                        'minimal_fee'],
                    properties: [
                        new OA\Property(property: 'vendor_id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'skill_id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'src_lang_classifier_value_id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'dst_lang_classifier_value_id', type: 'string', format: 'uuid'),
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
class PriceBulkCreateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'data' => 'required|array|min:1',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            collect($this->data)->each(function ($element, $index) use ($validator) {
                NestedFormRequestValidator::formRequest(new PriceCreateRequest())
                    ->setData($element)
                    ->validate()
                    ->setMessagesToValidator($validator, "data.$index");
            });
        });
    }
}
