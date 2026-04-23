<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'InstitutionPartner',
    required: [
        'id', 'institution_id', 'partner_institution_id',
        'created_at', 'updated_at',
    ],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'institution_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'partner_institution_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'discount_percentage_101', type: 'number', format: 'double', nullable: true),
        new OA\Property(property: 'discount_percentage_repetitions', type: 'number', format: 'double', nullable: true),
        new OA\Property(property: 'discount_percentage_100', type: 'number', format: 'double', nullable: true),
        new OA\Property(property: 'discount_percentage_95_99', type: 'number', format: 'double', nullable: true),
        new OA\Property(property: 'discount_percentage_85_94', type: 'number', format: 'double', nullable: true),
        new OA\Property(property: 'discount_percentage_75_84', type: 'number', format: 'double', nullable: true),
        new OA\Property(property: 'discount_percentage_50_74', type: 'number', format: 'double', nullable: true),
        new OA\Property(property: 'discount_percentage_0_49', type: 'number', format: 'double', nullable: true),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
    type: 'object'
)]
class InstitutionPartnerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'institution_id' => $this->institution_id,
            'partner_institution_id' => $this->partner_institution_id,
            'discount_percentage_101' => $this->discount_percentage_101,
            'discount_percentage_repetitions' => $this->discount_percentage_repetitions,
            'discount_percentage_100' => $this->discount_percentage_100,
            'discount_percentage_95_99' => $this->discount_percentage_95_99,
            'discount_percentage_85_94' => $this->discount_percentage_85_94,
            'discount_percentage_75_84' => $this->discount_percentage_75_84,
            'discount_percentage_50_74' => $this->discount_percentage_50_74,
            'discount_percentage_0_49' => $this->discount_percentage_0_49,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
