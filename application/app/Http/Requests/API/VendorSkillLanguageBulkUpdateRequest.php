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
                    required: ['id'],
                    properties: [
                        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'skill_id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'src_lang_classifier_value_id', type: 'string', format: 'uuid'),
                        new OA\Property(property: 'dst_lang_classifier_value_id', type: 'string', format: 'uuid'),
                    ]
                ),
                minItems: 1
            ),
        ]
    )
)]
class VendorSkillLanguageBulkUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'data' => 'required|array|min:1',
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                collect($this->input('data'))->each(function ($element, $index) use ($validator): void {
                    NestedFormRequestValidator::formRequest(new VendorSkillLanguageUpdateRequest())
                        ->setData($element)
                        ->validate()
                        ->setMessagesToValidator($validator, "data.$index");
                });
            },
        ];
    }
}
