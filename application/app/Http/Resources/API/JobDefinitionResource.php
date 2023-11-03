<?php

namespace App\Http\Resources\API;

use App\Enums\JobKey;
use App\Models\JobDefinition;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin JobDefinition
 */
#[OA\Schema(
    title: 'JobDefinition',
    required: [
        'id',
        'job_key',
        'multi_assignments_enabled',
        'linking_with_cat_tool_jobs_enabled',
    ],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'job_key', type: 'string', enum: JobKey::class),
        new OA\Property(property: 'skill_id', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'multi_assignments_enabled', type: 'boolean'),
        new OA\Property(property: 'linking_with_cat_tool_jobs_enabled', type: 'boolean'),
        new OA\Property(property: 'skill', ref: SkillResource::class, type: 'object', nullable: true),
    ],
    type: 'object'
)]
class JobDefinitionResource extends JsonResource
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
                'job_key',
                'skill_id',
                'multi_assignments_enabled',
                'linking_with_cat_tool_jobs_enabled'
            ),
            'skill' => SkillResource::make($this->whenLoaded('skill')),
        ];
    }
}
