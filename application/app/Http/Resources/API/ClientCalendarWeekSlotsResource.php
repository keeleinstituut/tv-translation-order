<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'Client Calendar Week Slots',
    properties: [
        new OA\Property(property: 'slots', type: 'array', items: new OA\Items(ref: ClientCalendarWeekAggregationResource::class)),
    ],
    type: 'object'
)]
class ClientCalendarWeekSlotsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slots' => ClientCalendarWeekAggregationResource::collection(
                $this->resource['slots']
            ),
        ];
    }
}
