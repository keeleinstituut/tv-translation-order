<?php

namespace App\Http\Resources\API;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * Per-day summary of a vendor's calendar.
 *
 * Input structure (array-based resource):
 * [
 *     'date'           => string (Y-m-d),
 *     'booked_hours'   => float|null,
 *     'total_hours'    => float|null,
 *     'is_emergency'   => bool,
 *     'is_fully_booked' => bool,
 * ]
 */
#[OA\Schema(
    title: 'Vendor Calendar Day Summary',
    properties: [
        new OA\Property(property: 'date', type: 'string', format: 'date'),
        new OA\Property(property: 'booked_hours', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'total_hours', type: 'number', format: 'float', nullable: true),
        new OA\Property(property: 'is_emergency', type: 'boolean'),
        new OA\Property(property: 'is_fully_booked', type: 'boolean'),
    ],
    type: 'object'
)]
class VendorCalendarDaySummaryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'date' => $this->resource['date'],
            'booked_hours' => $this->resource['booked_hours'],
            'total_hours' => $this->resource['total_hours'],
            'is_emergency' => $this->resource['is_emergency'],
            'is_fully_booked' => $this->resource['is_fully_booked'],
        ];
    }
}
