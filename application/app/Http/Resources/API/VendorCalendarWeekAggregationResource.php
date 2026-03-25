<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'Vendor Calendar Week Aggregation Slot',
    properties: [
        new OA\Property(property: 'language_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'start_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'end_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'calendar_entries', type: 'array', items: new OA\Items(ref: VendorCalendarEntryResource::class)),
    ],
    type: 'object'
)]
class VendorCalendarWeekAggregationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'language_id' => $this->resource['language_id'],
            'start_at' => $this->resource['start_at'],
            'end_at' => $this->resource['end_at'],
            'calendar_entries' => $this->resource['calendar_entries'],
        ];
    }
}
