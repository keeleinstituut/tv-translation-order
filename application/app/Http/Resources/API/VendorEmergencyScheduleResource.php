<?php

namespace App\Http\Resources\API;

use App\Models\VendorEmergencySchedule;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/** @mixin VendorEmergencySchedule */
#[OA\Schema(
    title: 'VendorEmergencySchedule',
    required: [
        'id',
        'vendor_id',
        'start_date',
        'end_date',
        'created_at',
    ],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'vendor_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'start_date', type: 'string', format: 'date'),
        new OA\Property(property: 'end_date', type: 'string', format: 'date'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
    type: 'object'
)]
class VendorEmergencyScheduleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vendor_id' => $this->vendor_id,
            'start_date' => $this->start_date->toDateString(),
            'end_date' => $this->end_date->toDateString(),
            'created_at' => $this->created_at,
        ];
    }
}
