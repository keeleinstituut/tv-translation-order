<?php

namespace App\Http\Resources\API;

use App\Enums\ProjectStatus;
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
        'deadline_at',
        'type_classifier_value',
        'tags',
        'cost',
        'source_language_classifier_value',
        'destination_language_classifier_values',
        'status',
    ],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'ext_id', type: 'string'),
        new OA\Property(property: 'reference_number', type: 'string', nullable: true),
        new OA\Property(property: 'institution_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'deadline_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'type_classifier_value', ref: ClassifierValueResource::class),
        new OA\Property(property: 'tags', type: 'array', items: new OA\Items(ref: TagResource::class)),
        new OA\Property(property: 'source_language_classifier_value', ref: ClassifierValueResource::class),
        new OA\Property(property: 'destination_languages_classifier_values', type: 'array', items: new OA\Items(ref: ClassifierValueResource::class)),
        new OA\Property(property: 'status', type: 'string', enum: ProjectStatus::class),
        new OA\Property(property: 'cost', description: 'TODO (computation/enumeration of cost is unclear for now)', anyOf: [new OA\Schema(const: null)]),
    ],
    type: 'object'
)]
class ProjectSummaryResource extends JsonResource
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
                'deadline_at',
                'status'
            ),
            'type_classifier_value' => ClassifierValueResource::make($this->typeClassifierValue),
            'tags' => TagResource::collection($this->tags),
            'source_language_classifier_value' => ClassifierValueResource::make($this->getSourceLanguageClassifierValue()),
            'destination_language_classifier_values' => ClassifierValueResource::collection($this->getDestinationLanguageClassifierValues()),
//            'cost' => $this->computeCost(),
        ];
    }
}
