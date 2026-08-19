<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * Vendor day view: own timeline with calendar entries.
 *
 * Input structure (array-based resource):
 * [
 *     'calendar_entries' => Collection<VendorCalendarEntry>,
 * ]
 */
#[OA\Schema(
    title: 'Vendor Calendar Day',
    properties: [
        new OA\Property(property: 'calendar_entries', type: 'array', items: new OA\Items(ref: VendorCalendarEntryResource::class)),
    ],
    type: 'object'
)]
class VendorCalendarDayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'calendar_entries' => VendorCalendarEntryResource::collection($this->resource['calendar_entries']),
        ];
    }
}
