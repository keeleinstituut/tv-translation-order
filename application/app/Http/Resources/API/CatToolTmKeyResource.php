<?php

namespace App\Http\Resources\API;

use App\Models\CatToolTmKey;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin CatToolTmKey
 */
#[OA\Schema(
    title: 'CatToolTmKey',
    required: [
        'id',
        'sub_project_id',
        'key',
        'is_writable',
    ],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'sub_project_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'key', description: 'In case of NecTM the `key` will be uuid of the tag', type: 'string'),
        new OA\Property(property: 'is_writable', type: 'boolean'),
    ],
    type: 'object'
)]
class CatToolTmKeyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->only(
                'id',
                'sub_project_id',
                'key',
                'is_writable',
            ),
            'sub_project' => $this->whenLoaded('subProject'),
        ];
    }
}
