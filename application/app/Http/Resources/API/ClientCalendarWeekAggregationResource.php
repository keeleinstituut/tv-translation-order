<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'Client Calendar Week Aggregation Slot',
    properties: [
        new OA\Property(property: 'language_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'start_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'end_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'total_vendors', type: 'integer'),
        new OA\Property(property: 'available_vendors', type: 'integer'),
    ],
    type: 'object'
)]
class ClientCalendarWeekAggregationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'language_id' => $this->resource['language_id'],
            'start_at' => $this->resource['start_at'],
            'end_at' => $this->resource['end_at'],
            'total_vendors' => $this->resource['total_vendors'],
            'available_vendors' => $this->resource['available_vendors'],
        ];
    }
}
