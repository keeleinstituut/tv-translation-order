<?php

namespace App\Http\Requests\API;

use App\Enums\TagType;
use App\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: [],
        properties: [
            new OA\Property(property: 'comment', type: 'string'),
            new OA\Property(property: 'company_name', type: 'string'),
            new OA\Property(property: 'discount_percentage_101', type: 'string'),
            new OA\Property(property: 'discount_percentage_repetitions', type: 'string'),
            new OA\Property(property: 'discount_percentage_100', type: 'string'),
            new OA\Property(property: 'discount_percentage_95_99', type: 'string'),
            new OA\Property(property: 'discount_percentage_85_94', type: 'string'),
            new OA\Property(property: 'discount_percentage_75_84', type: 'string'),
            new OA\Property(property: 'discount_percentage_50_74', type: 'string'),
            new OA\Property(property: 'discount_percentage_0_49', type: 'string'),
            new OA\Property(
                property: 'tags',
                type: 'array',
                items: new OA\Items(
                    type: 'string',
                    format: 'uuid',
                ),
                minItems: 1
            ),
        ]
    )
)]
class VendorUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        $percentageRule = 'sometimes|decimal:0,2|between:0,100.00';

        return [
            'tags' => 'sometimes|array',
            'tags.*' => [
                'required',
                Rule::exists(Tag::class, 'id')->where('type', TagType::Vendor->value),
            ],
            'comment' => 'sometimes|nullable|string',
            'company_name' => 'sometimes|nullable|string',

            'discount_percentage_101' => $percentageRule,
            'discount_percentage_repetitions' => $percentageRule,
            'discount_percentage_100' => $percentageRule,
            'discount_percentage_95_99' => $percentageRule,
            'discount_percentage_85_94' => $percentageRule,
            'discount_percentage_75_84' => $percentageRule,
            'discount_percentage_50_74' => $percentageRule,
            'discount_percentage_0_49' => $percentageRule,
        ];
    }
}
