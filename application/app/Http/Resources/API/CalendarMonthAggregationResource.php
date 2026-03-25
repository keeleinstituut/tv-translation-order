<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * Aggregated calendar slot for month view (daily slots).
 *
 * Each item represents one (language, date) pair with the total vendor hours available.
 */
#[OA\Schema(
    title: 'Calendar Month Aggregation Slot',
    properties: [
        new OA\Property(property: 'language_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'date', type: 'string', format: 'date'),
        new OA\Property(property: 'vendor_hours', type: 'number', format: 'float'),
    ],
    type: 'object'
)]
class CalendarMonthAggregationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'language_id' => $this->resource['language_id'],
            'vendor_hours' => $this->resource['vendor_hours'],
            'date' => $this->resource['date'],
        ];
    }
}
