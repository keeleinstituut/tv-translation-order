<?php

namespace App\Http\Resources;

use App\Enums\TaskType;
use App\Http\Resources\API\AssignmentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

#[OA\Schema(
    required: [
        'id',
        'task_type',
    ],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'task_type', type: 'string', format: 'enum', enum: TaskType::class),
        new OA\Property(property: 'project_id', type: 'string', format: 'uuid'),
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
            'assignee_institution_user_id' => data_get($this, 'task.assignee'),
            'task_type' => data_get($this, 'variables.task_type'),
            'project_id' => data_get($this, 'variables.project_id'),
            'assignment' => AssignmentResource::make(data_get($this, 'assignment')),
        ];
    }
}
