<?php

namespace App\Http\Resources\API;

use App\Models\VendorCalendarEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin VendorCalendarEntry
 */
#[OA\Schema(
    title: 'Vendor Calendar Entry',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'vendor_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'start_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'end_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'type', type: 'string',
            enum: ['assignment', 'prebook', 'external_calendar', 'vacation']),
        new OA\Property(property: 'assignment_id', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'assignment', ref: AssignmentResource::class, nullable: true),
    ],
    type: 'object'
)]
class VendorCalendarEntryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vendor_id' => $this->vendor_id,
            'start_at' => $this->start_at,
            'end_at' => $this->end_at,
            'type' => $this->type,
            'assignment_id' => $this->assignment_id,
            'assignment' => AssignmentResource::make($this->whenLoaded('assignment')),
        ];
    }
}
