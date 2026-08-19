<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * Client day view: language-tagged available/booked slots (1h grid),
 * calendar entries belonging to the client, and unassigned projects.
 *
 * Input structure (array-based resource):
 * [
 *     'available_slots' => array<array{start_at: string, end_at: string, languages: string[]}>,
 *     'calendar_entries' => Collection<VendorCalendarEntry>,
 *     'unassigned_projects' => Collection<Project>,
 * ]
 */
#[OA\Schema(
    title: 'Client Calendar Day',
    properties: [
        new OA\Property(property: 'available_slots', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'start_at', type: 'string', format: 'date-time'),
            new OA\Property(property: 'end_at', type: 'string', format: 'date-time'),
            new OA\Property(property: 'languages', type: 'array', items: new OA\Items(type: 'string', format: 'uuid')),
        ])),
        new OA\Property(property: 'calendar_entries', type: 'array', items: new OA\Items(ref: VendorCalendarEntryResource::class)),
        new OA\Property(property: 'unassigned_projects', type: 'array', items: new OA\Items(ref: UnassignedProjectCalendarResource::class)),
    ],
    type: 'object'
)]
class CalendarClientDayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'available_slots' => $this->resource['available_slots'] ?? [],
            'calendar_entries' => VendorCalendarEntryResource::collection(
                collect($this->resource['calendar_entries'] ?? [])
            ),
            'unassigned_projects' => UnassignedProjectCalendarResource::collection(
                collect($this->resource['unassigned_projects'] ?? [])
            ),
        ];
    }
}
