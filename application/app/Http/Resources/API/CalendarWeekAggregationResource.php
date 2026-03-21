<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * Aggregated calendar slot for week view (6h slots).
 *
 * Items always include language_id, start_at, end_at.
 * Optional fields depend on the role: total_vendors/available_vendors (client),
 * calendar_entries (vendor).
 */
#[OA\Schema(
    title: 'Calendar Week Aggregation Slot',
    properties: [
        new OA\Property(property: 'language_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'start_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'end_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'total_vendors', type: 'integer', nullable: true),
        new OA\Property(property: 'available_vendors', type: 'integer', nullable: true),
        new OA\Property(property: 'calendar_entries', type: 'array', items: new OA\Items(ref: VendorCalendarEntryResource::class), nullable: true),
    ],
    type: 'object'
)]
class CalendarWeekAggregationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'language_id' => $this->resource['language_id'],
            'start_at' => $this->resource['start_at'],
            'end_at' => $this->resource['end_at'],
            'total_vendors' => $this->when(isset($this->resource['total_vendors']), $this->resource['total_vendors'] ?? null),
            'available_vendors' => $this->when(isset($this->resource['available_vendors']), $this->resource['available_vendors'] ?? null),
            'calendar_entries' => $this->when(isset($this->resource['calendar_entries']), $this->resource['calendar_entries'] ?? null),
        ];
    }
}
