<?php

namespace App\Http\Resources\API;

use App\Models\CatToolJob;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin CatToolJob
 */
#[OA\Schema(
    required: [
        'id',
        'name',
        'ext_id',
        'progress_percentage',
        'translate_url',
    ],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'ext_id', type: 'string'),
        new OA\Property(property: 'progress_percentage', type: 'integer'),
        new OA\Property(property: 'translate_url', type: 'string', format: 'url'),
    ],
    type: 'object'
)]
class CatToolJobResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->only([
                'id',
                'name',
                'ext_id',
                'progress_percentage',
                'translate_url',
            ]),
        ];
    }
}
