<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * TPM month view: daily booked slots with per-vendor hours, plus vendor metadata.
 *
 * Input structure (array-based resource):
 * [
 *     'booked_slots' => array<array{language_id: string, date: string, vendors: array<string, float>}>,
 *     'vendors' => array<int, array{id: string, institutionUser: ?InstitutionUser, languages: string[], emergency_schedules: Collection}>,
 * ]
 */
#[OA\Schema(
    title: 'TPM Calendar Month',
    properties: [
        new OA\Property(property: 'available_slots', type: 'array', items: new OA\Items(properties: [
            new OA\Property(property: 'language_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'date', type: 'string', format: 'date'),
            new OA\Property(property: 'vendor_hours', type: 'object', additionalProperties: new OA\AdditionalProperties(type: 'number', format: 'float')),
        ])),
        new OA\Property(property: 'vendors', type: 'array', items: new OA\Items(ref: VendorCalendarExpandResource::class)),
    ],
    type: 'object'
)]
class CalendarMonthProjectManagerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'available_slots' => $this->resource['available_slots'],
            'vendors' => VendorCalendarExpandResource::collection($this->resource['vendors']),
        ];
    }
}
