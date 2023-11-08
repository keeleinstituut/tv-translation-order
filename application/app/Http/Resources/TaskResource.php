<?php

namespace App\Http\Resources;

use App\Http\Resources\API\AssignmentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    required: [
        'id',
        'assignment',
    ],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'assignment', ref: AssignmentResource::class),
    ],
    type: 'object'
)]
class TaskResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => data_get($this, 'task.id'),
            'assignment' => AssignmentResource::make(data_get($this, 'assignment')),
        ];
    }
}
