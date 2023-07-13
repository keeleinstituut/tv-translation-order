<?php

namespace App\Http\Resources;

use App\Enums\TagType;
use App\Models\Tag;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin Tag
 */
#[OA\Schema(
    title: 'Tag',
    required: ['id', 'institution_id', 'name', 'type', 'updated_at', 'created_at'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'institution_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'type', type: 'string', enum: TagType::class),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
    ],
    type: 'object'
)]
class TagResource extends JsonResource
{
    /**
     * @return array{
     *     id: string,
     *     institution_id: string,
     *     name: string,
     *     type: string,
     *     updated_at: Carbon,
     *     created_at: Carbon
     * }
     */
    public function toArray(Request $request): array
    {
        return $this->only([
            'id',
            'institution_id',
            'name',
            'type',
            'created_at',
            'updated_at',
        ]);
    }
}
