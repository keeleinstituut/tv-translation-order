<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'Vendor Calendar Week Slots',
    properties: [
        new OA\Property(property: 'slots', type: 'array', items: new OA\Items(ref: VendorCalendarWeekAggregationResource::class)),
    ],
    type: 'object'
)]
class VendorCalendarWeekSlotsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slots' => VendorCalendarWeekAggregationResource::collection($this->resource['slots']),
        ];
    }
}
