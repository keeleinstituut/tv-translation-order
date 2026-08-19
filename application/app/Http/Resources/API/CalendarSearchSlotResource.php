<?php

namespace App\Http\Resources\API;

use App\Enums\CalendarRole;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * The first available calendar slot matching the search criteria.
 *
 * Input structure (array-based resource):
 * [
 *     'start_at' => ?string (ISO 8601),
 *     'end_at' => ?string (ISO 8601),
 *     'vendor_ids' => string[],
 *     'language_id' => ?string (UUID),
 *     'role' => CalendarRole,
 * ]
 */
#[OA\Schema(
    title: 'Calendar Search Slot',
    properties: [
        new OA\Property(property: 'start_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'end_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'vendor_ids', type: 'array', items: new OA\Items(type: 'string', format: 'uuid'), nullable: true),
        new OA\Property(property: 'language_id', type: 'string', format: 'uuid', nullable: true),
    ],
    type: 'object'
)]
class CalendarSearchSlotResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $role = $this->resource['role'] ?? null;

        return [
            'language_id' => $this->resource['language_id'] ?? null,
            'start_at' => $this->resource['start_at'] ?? null,
            'end_at' => $this->resource['end_at'] ?? null,
            'vendor_ids' => $role === CalendarRole::ProjectManager
                ? ($this->resource['vendor_ids'] ?? null)
                : null,
        ];
    }
}
