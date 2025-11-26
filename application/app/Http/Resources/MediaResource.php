<?php

namespace App\Http\Resources;

use App\Http\Resources\API\AssignmentResource;
use App\Http\Resources\API\InstitutionUserResource;
use App\Models\Media;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin Media
 */
#[OA\Schema(
    title: 'Media',
    required: ['name', 'id', 'uuid', 'file_name', 'custom_properties', 'size', 'collection_name', 'created_at', 'updated_at'],
    properties: [
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(property: 'id', type: 'integer'),
        new OA\Property(property: 'uuid', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'file_name', type: 'string'),
        new OA\Property(
            property: 'custom_properties',
            properties: [
                new OA\Property(property: 'type', type: 'string', enum: Project::HELP_FILE_TYPES),
                new OA\Property(property: 'assignment_id', type: 'string', format: 'uuid', nullable: true),
                new OA\Property(property: 'institution_user_id', type: 'string', format: 'uuid', nullable: true),
            ],
            type: 'object'),
        new OA\Property(property: 'size', type: 'integer'),
        new OA\Property(
            property: 'collection_name',
            type: 'string',
            enum: [
                Project::HELP_FILES_COLLECTION,
                Project::SOURCE_FILES_COLLECTION,
                Project::FINAL_FILES_COLLECTION,
            ]
        ),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'assignment', ref: AssignmentResource::class, nullable: true),
        new OA\Property(property: 'institution_user', ref: InstitutionUserResource::class, nullable: true),
        new OA\Property(property: 'is_project_final_file', type: 'boolean', nullable: true),
    ],
    type: 'object'
)]
class MediaResource extends JsonResource
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
                'name',
                'id',
                'uuid',
                'file_name',
                'custom_properties',
                'size',
                'collection_name',
                'created_at',
                'updated_at',
            ),
            'assignment' => AssignmentResource::make($this->whenLoaded('assignment')),
            'institution_user' => InstitutionUserResource::make($this->whenLoaded('institutionUser')),
            ...$this->getSubProjectFinalFileIsProjectFinalFileField()
        ];
    }

    private function getSubProjectFinalFileIsProjectFinalFileField(): array
    {
        if ($this->relationLoaded('copies')) {
            return [
                'is_project_final_file' => $this->copies->contains(fn (Media $media) => $media->isProjectFinalFile())
            ];
        }

        return [];
    }
}
