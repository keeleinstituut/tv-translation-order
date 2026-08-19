<?php

namespace App\Http\Resources\API;

use App\Models\VendorCalendarImport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/** @mixin VendorCalendarImport */
#[OA\Schema(
    title: 'VendorCalendarImport',
    required: [
        'id',
        'vendor_id',
        'date_from',
        'date_to',
        'events_count',
        'created_at',
    ],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'vendor_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'date_from', type: 'string', format: 'date-time'),
        new OA\Property(property: 'date_to', type: 'string', format: 'date-time'),
        new OA\Property(property: 'events_count', type: 'integer'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
    type: 'object'
)]
class VendorCalendarImportResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vendor_id' => $this->vendor_id,
            'date_from' => $this->date_from,
            'date_to' => $this->date_to,
            'events_count' => $this->whenCounted('events'),
            'created_at' => $this->created_at,
        ];
    }
}
