<?php

namespace App\Http\Requests\API;

use App\Http\Resources\API\VolumeAnalysisDiscountResource;
use App\Http\Resources\API\VolumeAnalysisResource;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: [
            'unit_fee',
            'custom_volume_analysis',
            'discounts',
        ],
        properties: [
            new OA\Property(property: 'unit_fee', type: 'number', format: 'double', minimum: 0),
            new OA\Property(property: 'custom_volume_analysis', ref: VolumeAnalysisResource::class),
            new OA\Property(property: 'discounts', ref: VolumeAnalysisDiscountResource::class),
        ]
    )
)]
class CatToolVolumeUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        $percentageRule = 'sometimes|decimal:0,2|between:0,100.00';
        $unitQualityRule = ['sometimes', 'integer', 'min:0'];

        return [
            'unit_fee' => 'decimal:0,2|between:0,99999999.99',
            'custom_volume_analysis.tm_101' => $unitQualityRule,
            'custom_volume_analysis.repetitions' => $unitQualityRule,
            'custom_volume_analysis.tm_100' => $unitQualityRule,
            'custom_volume_analysis.tm_95_99' => $unitQualityRule,
            'custom_volume_analysis.tm_85_94' => $unitQualityRule,
            'custom_volume_analysis.tm_75_84' => $unitQualityRule,
            'custom_volume_analysis.tm_50_74' => $unitQualityRule,
            'custom_volume_analysis.tm_0_49' => $unitQualityRule,
            'discounts.discount_percentage_101' => $percentageRule,
            'discounts.discount_percentage_repetitions' => $percentageRule,
            'discounts.discount_percentage_100' => $percentageRule,
            'discounts.discount_percentage_95_99' => $percentageRule,
            'discounts.discount_percentage_85_94' => $percentageRule,
            'discounts.discount_percentage_75_84' => $percentageRule,
            'discounts.discount_percentage_50_74' => $percentageRule,
            'discounts.discount_percentage_0_49' => $percentageRule,
        ];
    }
}
