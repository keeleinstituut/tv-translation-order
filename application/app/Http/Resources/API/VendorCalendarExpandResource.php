<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * Vendor item in calendar vendor maps (day/week/month views).
 *
 * Expected input structure (array-based resource):
 * [
 *     'id' => string,
 *     'institutionUser' => ?InstitutionUser,
 *     'languages' => array<string>,
 *     'emergency_schedules' => Collection<VendorEmergencySchedule>,
 *     'calendar_entries' => ?Collection<VendorCalendarEntry>,  // Day only
 * ]
 */
#[OA\Schema(
    title: 'Calendar Vendor Map Item',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'institutionUser', ref: CalendarInstitutionUserResource::class, nullable: true),
        new OA\Property(property: 'languages', type: 'array', items: new OA\Items(type: 'string', format: 'uuid')),
        new OA\Property(property: 'emergency_schedules', type: 'array', items: new OA\Items(ref: VendorEmergencyScheduleResource::class)),
        new OA\Property(property: 'calendar_entries', type: 'array', items: new OA\Items(ref: VendorCalendarEntryResource::class)),
    ],
    type: 'object'
)]
class VendorCalendarExpandResource extends JsonResource
{
    /**
     * @param array $resource
     */
    public function __construct($resource)
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $result = [
            'id' => $this->resource['id'],
            'institutionUser' => CalendarInstitutionUserResource::make($this->resource['institutionUser']),
            'languages' => $this->resource['languages'],
            'emergency_schedules' => VendorEmergencyScheduleResource::collection($this->resource['emergency_schedules']),
        ];

        if (array_key_exists('calendar_entries', $this->resource)) {
            $result['calendar_entries'] = VendorCalendarEntryResource::collection($this->resource['calendar_entries']);
        }

        return $result;
    }
}
