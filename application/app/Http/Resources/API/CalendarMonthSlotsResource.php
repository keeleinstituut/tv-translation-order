<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * Wrapper for vendor/client month views: aggregation slots in an object envelope.
 *
 * Input structure (array-based resource):
 * [
 *     'slots' => Collection|array of month aggregation rows,
 * ]
 */
#[OA\Schema(
    title: 'Calendar Month Slots',
    properties: [
        new OA\Property(property: 'slots', type: 'array', items: new OA\Items(ref: CalendarMonthAggregationResource::class)),
    ],
    type: 'object'
)]
class CalendarMonthSlotsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'slots' => CalendarMonthAggregationResource::collection($this->resource['slots']),
        ];
    }
}
