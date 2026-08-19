<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    title: 'Prebook',
    properties: [
        new OA\Property(property: 'calendar_entry', ref: VendorCalendarEntryResource::class),
        new OA\Property(property: 'expires_at', type: 'string', format: 'date-time'),
    ],
    type: 'object'
)]
class PrebookResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'calendar_entry' => VendorCalendarEntryResource::make($this->resource['calendar_entry']),
            'expires_at' => $this->resource['expires_at'],
        ];
    }
}
