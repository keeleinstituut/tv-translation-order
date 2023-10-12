<?php

namespace App\Http\Resources\API;

use App\Http\Resources\MediaResource;
use App\Models\SubProject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin SubProject
 */
#[OA\Schema(
    required: [
        'id',
        'ext_id',
        'project_id',
        'created_at',
        'updated_at',
        'price',
        'features',
        'source_language_classifier_value_id',
        'destination_language_classifier_value_id',
        'cat_files',
        'mt_enabled',
    ],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'ext_id', type: 'string'),
        new OA\Property(property: 'project_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'deadline_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'price', type: 'number'),
        new OA\Property(property: 'features', type: 'array', items: new OA\Items(type: 'string')),
        new OA\Property(property: 'project', ref: ProjectResource::class, nullable: true),
        new OA\Property(property: 'source_language_classifier_value', ref: ClassifierValueResource::class, nullable: true),
        new OA\Property(property: 'source_language_classifier_value_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'destination_language_classifier_value', ref: ClassifierValueResource::class, nullable: true),
        new OA\Property(property: 'destination_language_classifier_value_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'assignments', type: 'array', items: new OA\Items(ref: AssignmentResource::class), nullable: true),
        new OA\Property(property: 'source_files', type: 'array', items: new OA\Items(ref: MediaResource::class), nullable: true),
        new OA\Property(property: 'final_files', type: 'array', items: new OA\Items(ref: MediaResource::class), nullable: true),
        new OA\Property(property: 'cat_files', type: 'array', items: new OA\Items(ref: MediaResource::class), nullable: true),
        new OA\Property(property: 'cat_jobs', type: 'array', items: new OA\Items(ref: CatToolJobResource::class), nullable: true),
        new OA\Property(property: 'mt_enabled', type: 'boolean'),
    ],
    type: 'object'
)]
class SubProjectResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ext_id' => $this->ext_id,
            'project_id' => $this->project_id,
            'deadline_at' => $this->deadline_at,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'price' => $this->price,
            'features' => $this->project->typeClassifierValue->projectTypeConfig->features,
            'project' => new ProjectResource($this->whenLoaded('project')),
            'source_language_classifier_value_id' => $this->source_language_classifier_value_id,
            'source_language_classifier_value' => new ClassifierValueResource($this->whenLoaded('sourceLanguageClassifierValue')),
            'destination_language_classifier_value_id' => $this->destination_language_classifier_value_id,
            'destination_language_classifier_value' => new ClassifierValueResource($this->whenLoaded('destinationLanguageClassifierValue')),
            'assignments' => AssignmentResource::collection($this->whenLoaded('assignments')),
            'source_files' => MediaResource::collection($this->whenLoaded('sourceFiles')),
            'final_files' => MediaResource::collection($this->whenLoaded('finalFiles')),
            'cat_files' => MediaResource::collection($this->cat()->getSourceFiles()),
            'cat_jobs' => CatToolJobResource::collection($this->whenLoaded('catToolJobs')),
            'mt_enabled' => $this->cat()->hasMtEnabled(),
        ];
    }
}
