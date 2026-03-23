<?php

namespace App\Http\Resources\API;

use App\Models\ProjectComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/** @mixin ProjectComment */
#[OA\Schema(
    title: 'ProjectComment',
    required: [
        'id',
        'project_id',
        'institution_user_id',
        'comment',
        'created_at',
        'updated_at',
    ],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'project_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'institution_user_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'comment', type: 'string'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
    ],
    type: 'object'
)]
class ProjectCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge(
            $this->only(
                'id',
                'project_id',
                'institution_user_id',
                'comment',
                'created_at',
                'updated_at',
            ), [
            'project' => ProjectResource::make($this->whenLoaded('project'))
        ]);
    }
}
