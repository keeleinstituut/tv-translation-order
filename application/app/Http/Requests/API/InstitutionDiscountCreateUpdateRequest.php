<?php

namespace App\Http\Requests\API;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: [
            'discount_percentage_101',
            'discount_percentage_repetitions',
            'discount_percentage_100',
            'discount_percentage_95_99',
            'discount_percentage_85_94',
            'discount_percentage_75_84',
            'discount_percentage_50_74',
            'discount_percentage_0_49',
        ],
        properties: [
            new OA\Property(property: 'discount_percentage_101', type: 'number', format: 'double'),
            new OA\Property(property: 'discount_percentage_repetitions', type: 'number', format: 'double'),
            new OA\Property(property: 'discount_percentage_100', type: 'number', format: 'double'),
            new OA\Property(property: 'discount_percentage_95_99', type: 'number', format: 'double'),
            new OA\Property(property: 'discount_percentage_85_94', type: 'number', format: 'double'),
            new OA\Property(property: 'discount_percentage_75_84', type: 'number', format: 'double'),
            new OA\Property(property: 'discount_percentage_50_74', type: 'number', format: 'double'),
            new OA\Property(property: 'discount_percentage_0_49', type: 'number', format: 'double'),
        ]
    )
)]
class InstitutionDiscountCreateUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        $percentageRule = ['required', 'decimal:0,2', 'between:0,100.00'];

        return [
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
