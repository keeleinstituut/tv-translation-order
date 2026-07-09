<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'OutsourceRequestPreviewOffer',
    properties: [
        new OA\Property(property: 'institution_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'price', type: 'number', format: 'double', nullable: true),
    ],
    type: 'object'
)]
class OutsourceRequestPreviewOfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'institution_id' => $this->resource['institution_id'],
            'price' => $this->resource['price'],
        ];
    }
}
