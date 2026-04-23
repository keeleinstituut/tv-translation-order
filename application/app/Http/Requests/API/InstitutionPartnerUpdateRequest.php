<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'discount_percentage_101', type: 'number', format: 'double', nullable: true),
            new OA\Property(property: 'discount_percentage_repetitions', type: 'number', format: 'double', nullable: true),
            new OA\Property(property: 'discount_percentage_100', type: 'number', format: 'double', nullable: true),
            new OA\Property(property: 'discount_percentage_95_99', type: 'number', format: 'double', nullable: true),
            new OA\Property(property: 'discount_percentage_85_94', type: 'number', format: 'double', nullable: true),
            new OA\Property(property: 'discount_percentage_75_84', type: 'number', format: 'double', nullable: true),
            new OA\Property(property: 'discount_percentage_50_74', type: 'number', format: 'double', nullable: true),
            new OA\Property(property: 'discount_percentage_0_49', type: 'number', format: 'double', nullable: true),
        ]
    )
)]
class InstitutionPartnerUpdateRequest extends FormRequest
{
    public function rules(): array
    {
        $discountRule = 'sometimes|nullable|decimal:0,2|between:0,100.00';

        return [
            'discount_percentage_101' => $discountRule,
            'discount_percentage_repetitions' => $discountRule,
            'discount_percentage_100' => $discountRule,
            'discount_percentage_95_99' => $discountRule,
            'discount_percentage_85_94' => $discountRule,
            'discount_percentage_75_84' => $discountRule,
            'discount_percentage_50_74' => $discountRule,
            'discount_percentage_0_49' => $discountRule,
        ];
    }
}
