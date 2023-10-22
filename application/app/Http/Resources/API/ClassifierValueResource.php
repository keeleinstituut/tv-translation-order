<?php

namespace App\Http\Resources\API;

use App\Models\CachedEntities\ClassifierValue;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin ClassifierValue
 */
#[OA\Schema(
    title: 'Classifier Value',
    required: ['id', 'type', 'value', 'name'],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'type', type: 'string'),
        new OA\Property(property: 'value', type: 'string'),
        new OA\Property(property: 'name', type: 'string'),
        new OA\Property(
            property: 'meta',
            description: 'Additional data depending on the classifier type',
            anyOf: [
                new OA\Schema(
                    required: ['iso3_code'],
                    properties: [
                        new OA\Property(property: 'iso3_code', type: 'string'),
                    ],
                    type: 'object'
                ),
                new OA\Schema(
                    required: ['code'],
                    properties: [
                        new OA\Property(property: 'code', type: 'string'),
                    ],
                    type: 'object'
                ),
            ]
        ),
        new OA\Property(
            property: 'project_type_config',
            ref: ProjectTypeConfigResource::class,
            description: 'config of PROJECT_TYPE classifier values',
            type: 'object',
            nullable: true
        )
    ],
    type: 'object'
)]
class ClassifierValueResource extends JsonResource
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
                'type',
                'value',
                'name',
                'meta',
            ),
            'project_type_config' => ProjectTypeConfigResource::make($this->whenLoaded('projectTypeConfig'))
        ];
    }
}
