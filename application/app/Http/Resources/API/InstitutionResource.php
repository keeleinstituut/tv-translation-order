<?php

namespace App\Http\Resources\API;

use App\Models\CachedEntities\Institution;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin Institution
 */
#[OA\Schema(
    title: 'Institution',
    required: ['id', 'name', 'short_name'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'name', type: 'string', nullable: true),
        new OA\Property(property: 'short_name', type: 'string', nullable: true),
        new OA\Property(property: 'email', type: 'string', format: 'email', nullable: true),
        new OA\Property(property: 'phone', type: 'string', nullable: true),
        new OA\Property(property: 'logo_url', type: 'string', nullable: true),
        new OA\Property(property: 'institution_type', type: 'string', nullable: true),
    ],
    type: 'object'
)]
class InstitutionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'short_name' => $this->short_name,
            'email' => $this->email,
            'phone' => $this->phone,
            'logo_url' => $this->logo_url,
            'institution_type' => $this->institution_type,
        ];
    }
}
