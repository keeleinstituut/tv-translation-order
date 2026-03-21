<?php

namespace App\Http\Resources\API;

use App\Models\Vendor;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin Vendor
 */
#[OA\Schema(
    title: 'Slot Matching Vendor',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'institution_user_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'name', type: 'string', nullable: true),
        new OA\Property(property: 'is_internal', type: 'boolean'),
    ],
    type: 'object'
)]
class SlotMatchingVendorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'institution_user_id' => $this->institution_user_id,
            'name' => $this->institutionUser?->getUserFullName(),
            'is_internal' => $this->is_internal
        ];
    }
}
