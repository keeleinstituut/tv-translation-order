<?php

namespace App\Http\Resources\API;

use App\Enums\VolumeUnits;
use App\Models\Volume;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/** @mixin Volume */
#[OA\Schema(
    title: 'Volume',
    required: ['id', 'assignment_id', 'created_at', 'updated_at', 'cat_chunk_identifier', 'unit_type', 'unit_quantity', 'unit_fee'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'assignment_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'cat_chunk_identifier', type: 'string', nullable: true),
        new OA\Property(property: 'unit_type', type: 'string', enum: VolumeUnits::class),
        new OA\Property(property: 'unit_quantity', type: 'number', minimum: 0),
        new OA\Property(property: 'unit_fee', type: 'number', minimum: 0),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
    type: 'object'
)]
class VolumeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return $this->only(
           'id',
           'assignment_id',
           'created_at',
           'updated_at',
           'cat_chunk_identifier',
           'unit_type',
           'unit_quantity',
           'unit_fee',
        );
    }
}
