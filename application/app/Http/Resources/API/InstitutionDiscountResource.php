<?php

namespace App\Http\Resources\API;

use App\Models\InstitutionDiscount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin InstitutionDiscount
 */
#[OA\Schema(
    title: 'InstitutionDiscount',
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
    ],
    type: 'object'
)]
class InstitutionDiscountResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->only(
            'discount_percentage_101',
            'discount_percentage_repetitions',
            'discount_percentage_100',
            'discount_percentage_95_99',
            'discount_percentage_85_94',
            'discount_percentage_75_84',
            'discount_percentage_50_74',
            'discount_percentage_0_49'
        );
    }
}
