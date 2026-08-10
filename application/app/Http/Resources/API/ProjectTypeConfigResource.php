<?php

namespace App\Http\Resources\API;

use App\Models\ProjectTypeConfig;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin ProjectTypeConfig
 */
#[OA\Schema(
    title: 'ProjectTypeConfig',
    required: [
        'id',
        'type_classifier_value_id',
        'features',
        'is_start_date_supported',
        'cat_tool_enabled',
    ],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'type_classifier_value_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'features', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'is_start_date_supported', type: 'boolean'),
        new OA\Property(property: 'cat_tool_enabled', type: 'boolean'),
        new OA\Property(property: 'type_classifier_value', ref: ClassifierValueResource::class, type: 'object', nullable: true),
        new OA\Property(property: 'job_definitions', type: 'array', items: new OA\Items(ref: JobDefinitionResource::class)),
    ],
    type: 'object'
)]
class ProjectTypeConfigResource extends JsonResource
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
                'type_classifier_value_id',
                'features',
                'is_start_date_supported',
                'cat_tool_enabled',
            ),
            'type_classifier_value' => ClassifierValueResource::make($this->whenLoaded('typeClassifierValue')),
            'job_definitions' => JobDefinitionResource::collection($this->whenLoaded('jobDefinitions')),
        ];
    }
}
