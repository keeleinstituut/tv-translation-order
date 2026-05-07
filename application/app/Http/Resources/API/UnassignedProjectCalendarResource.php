<?php

namespace App\Http\Resources\API;

use App\Enums\ProjectStatus;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use OpenApi\Attributes as OA;

/**
 * @mixin Project
 */
#[OA\Schema(
    title: 'Unassigned Project Calendar Entry',
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'ext_id', type: 'string'),
        new OA\Property(property: 'event_start_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'event_end_at', type: 'string', format: 'date-time', nullable: true),
        new OA\Property(property: 'status', type: 'string', enum: ProjectStatus::class),
        new OA\Property(property: 'service_type', type: 'string', nullable: true),
        new OA\Property(property: 'location', type: 'string', nullable: true),
        new OA\Property(property: 'meeting_link', type: 'string', nullable: true),
        new OA\Property(property: 'source_language_classifier_value_id', type: 'string', format: 'uuid', nullable: true),
        new OA\Property(property: 'destination_language_classifier_value_ids', type: 'array', items: new OA\Items(type: 'string', format: 'uuid')),
    ],
    type: 'object'
)]
class UnassignedProjectCalendarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $subProjects = $this->whenLoaded('subProjects', $this->subProjects, collect());

        return [
            'id' => $this->id,
            'ext_id' => $this->ext_id,
            'event_start_at' => $this->event_start_at,
            'event_end_at' => $this->event_end_at,
            'status' => $this->status->value,
            'service_type' => $this->service_type,
            'location' => $this->location,
            'meeting_link' => $this->meeting_link,
            'source_language_classifier_value_id' => $subProjects->first()?->source_language_classifier_value_id,
            'destination_language_classifier_value_ids' => $subProjects
                ->pluck('destination_language_classifier_value_id')
                ->unique()
                ->values()
                ->all(),
        ];
    }
}
