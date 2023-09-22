<?php

namespace App\Http\Resources\API;

use App\Enums\ProjectStatus;
use App\Http\Resources\MediaResource;
use App\Http\Resources\TagResource;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin Project
 */
#[OA\Schema(
    required: [
        'id',
        'ext_id',
        'reference_number',
        'institution_id',
        'comments',
        'workflow_template_id',
        'workflow_instance_ref',
        'deadline_at',
        'event_start_at',
        'created_at',
        'updated_at',
        'manager_institution_user',
        'client_institution_user',
        'type_classifier_value',
        'translation_domain_classifier_value',
        'sub_project_ids',
        'source_files',
        'help_files',
        'final_files',
        'tags',
        'source_language_classifier_value',
        'destination_language_classifier_values',
        'status',
        'cost',
    ],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'ext_id', type: 'string'),
        new OA\Property(property: 'reference_number', type: 'string', nullable: true),
        new OA\Property(property: 'institution_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'comments', type: 'string', nullable: true),
        new OA\Property(property: 'deadline_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'event_start_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'manager_institution_user', ref: InstitutionUserResource::class, nullable: true),
        new OA\Property(property: 'client_institution_user', ref: InstitutionUserResource::class),
        new OA\Property(property: 'type_classifier_value', ref: ClassifierValueResource::class),
        new OA\Property(property: 'translation_domain_classifier_value', ref: ClassifierValueResource::class),
        new OA\Property(property: 'sub_project_ids', type: 'array', items: new OA\Items(type: 'string', format: 'uuid')),
        new OA\Property(property: 'source_files', type: 'array', items: new OA\Items(ref: MediaResource::class)),
        new OA\Property(property: 'help_files', type: 'array', items: new OA\Items(ref: MediaResource::class)),
        new OA\Property(property: 'final_files', type: 'array', items: new OA\Items(ref: MediaResource::class)),
        new OA\Property(property: 'tags', type: 'array', items: new OA\Items(ref: TagResource::class)),
        new OA\Property(property: 'source_language_classifier_value', ref: ClassifierValueResource::class),
        new OA\Property(property: 'destination_languages_classifier_values', type: 'array', items: new OA\Items(ref: ClassifierValueResource::class)),
        new OA\Property(property: 'status', type: 'string', enum: ProjectStatus::class),
        new OA\Property(property: 'cost', description: 'TODO (computation/enumeration of costs is unclear for now)', anyOf: [new OA\Schema(const: null)]),
    ],
    type: 'object'
)
] class ProjectResource extends JsonResource
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
                'ext_id',
                'reference_number',
                'institution_id',
                'comments',
                'workflow_template_id',
                'workflow_instance_ref',
                'deadline_at',
                'event_start_at',
                'status',
                'created_at',
                'updated_at',
            ),
            'manager_institution_user' => InstitutionUserResource::make($this->whenLoaded('managerInstitutionUser')),
            'client_institution_user' => InstitutionUserResource::make($this->whenLoaded('clientInstitutionUser')),
            'type_classifier_value' => ClassifierValueResource::make($this->whenLoaded('typeClassifierValue')),
            'translation_domain_classifier_value' => ClassifierValueResource::make($this->whenLoaded('translationDomainClassifierValue')),
            'source_files' => MediaResource::collection($this->whenLoaded('sourceFiles')),
            'help_files' => MediaResource::collection($this->whenLoaded('helpFiles')),
            'final_files' => MediaResource::collection($this->whenLoaded('finalFiles')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
            'cost' => $this->computeCost(),
            'sub_projects' => SubProjectResource::collection($this->whenLoaded('subProjects')),
        ];
    }
}
