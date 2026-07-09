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
                    required: [
                        'skill_id', 'src_lang_classifier_value_id', 'dst_lang_classifier_value_id',
                        'character_fee', 'word_fee', 'page_fee', 'minute_fee', 'hour_fee', 'minimal_fee',
                    ],
                    properties: [
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
class InstitutionPriceBulkCreateRequest extends FormRequest
{
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
                NestedFormRequestValidator::formRequest(new InstitutionPriceCreateRequest())
                    ->setData($element)
                    ->validate()
                    ->setMessagesToValidator($validator, "data.$index");
            });

            // Check within-batch uniqueness on (src, dst, skill) tuples
            $tuples = collect($this->data)->map(fn ($item) => implode('|', [
                $item['src_lang_classifier_value_id'] ?? '',
                $item['dst_lang_classifier_value_id'] ?? '',
                $item['skill_id'] ?? '',
            ]));

            $duplicates = $tuples->filter(fn ($tuple, $index) => $tuples->take($index)->contains($tuple));

            $duplicates->keys()->each(function ($index) use ($validator) {
                $msg = 'Duplicate language pair and skill within the batch';
                $validator->errors()
                    ->add("data.$index.src_lang_classifier_value_id", $msg)
                    ->add("data.$index.dst_lang_classifier_value_id", $msg)
                    ->add("data.$index.skill_id", $msg);
            });
        });
    }
}
