<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * TPM day view: vendor-centric available slots and per-vendor metadata list.
 *
 * Input structure (array-based resource):
 * [
 *     'available_slots' => array<array{start_at: string, end_at: string, vendor_ids: string[]}>,
 *     'vendors' => array<int, array{id: string, institutionUser: ?InstitutionUser, calendar_entries: Collection, languages: string[], emergency_schedules: Collection}>,
 * ]
 */
#[OA\Schema(
    title: 'TPM Calendar Day',
    properties: [
        new OA\Property(property: 'available_slots', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'start_at', type: 'string', format: 'date-time'),
            new OA\Property(property: 'end_at', type: 'string', format: 'date-time'),
            new OA\Property(property: 'vendor_ids', type: 'array', items: new OA\Items(type: 'string', format: 'uuid')),
        ])),
        new OA\Property(property: 'vendors', type: 'array', items: new OA\Items(ref: VendorCalendarExpandResource::class)),
        new OA\Property(property: 'unassigned_projects', type: 'array', items: new OA\Items(ref: UnassignedProjectCalendarResource::class)),
    ],
    type: 'object'
)]
class CalendarDayProjectManagerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'available_slots' => $this->resource['available_slots'],
            'vendors' => VendorCalendarExpandResource::collection($this->resource['vendors']),
            'unassigned_projects' => UnassignedProjectCalendarResource::collection(
                collect($this->resource['unassigned_projects'] ?? [])
            ),
        ];
    }
}
