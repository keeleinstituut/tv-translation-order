<?php

namespace App\Http\Resources\API;

use App\Enums\AssignmentStatus;
use App\Enums\JobKey;
use App\Models\Assignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use OpenApi\Attributes as OA;

/**
 * @mixin Assignment
 */
#[OA\Schema(
    title: 'Assignment',
    required: [
        'id',
        'sub_project_id',
        'assigned_vendor_id',
        'ext_id',
        'deadline_at',
        'comments',
        'assignee_comments',
        'job_definition',
        'status',
        'created_at',
        'updated_at',
    ],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'sub_project_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'ext_id', type: 'string'),
        new OA\Property(property: 'deadline_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'event_start_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'comments', type: 'string'),
        new OA\Property(property: 'status', type: 'string', format: 'enum', enum: AssignmentStatus::class),
        new OA\Property(property: 'price', type: 'number', nullable: true),
        new OA\Property(property: 'assignee_comments', type: 'string'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'job_definition', ref: JobDefinitionResource::class, nullable: true),
        new OA\Property(property: 'assignee', ref: VendorResource::class, nullable: true),
        new OA\Property(property: 'candidates', type: 'array', items: new OA\Items(ref: CandidateResource::class), nullable: true),
        new OA\Property(property: 'volumes', type: 'array', items: new OA\Items(ref: VolumeResource::class), nullable: true),
        new OA\Property(property: 'cat_jobs', type: 'array', items: new OA\Items(ref: CatToolJobResource::class), nullable: true),
        new OA\Property(property: 'subProject', ref: SubProjectResource::class, nullable: true),
        new OA\Property(property: 'outsource_requests', type: 'array', items: new OA\Items(ref: OutsourceRequestResource::class)),
        new OA\Property(property: 'manager_candidates', type: 'array', items: new OA\Items(ref: ProjectManagerCandidateResource::class)),
        new OA\Property(property: 'can_download_xliff', type: 'boolean'),
        new OA\Property(property: 'can_download_translations', type: 'boolean'),
    ],
    type: 'object'
)]
class AssignmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $subProject = $this->relationLoaded('subProject') ? $this->subProject : null;

        return [
            ...$this->only(
                'id',
                'sub_project_id',
                'ext_id',
                'status',
                'price',
                'deadline_at',
                'event_start_at',
                'comments',
                'assignee_comments',
                'created_at',
                'updated_at',
            ),
            'job_definition' => JobDefinitionResource::make($this->whenLoaded('jobDefinition')),
            'assignee' => VendorResource::make($this->whenLoaded('assignee')),
            'candidates' => CandidateResource::collection($this->whenLoaded('candidates')),
            'volumes' => VolumeResource::collection($this->whenLoaded('volumes')),
            'cat_jobs' => CatToolJobResource::collection($this->whenLoaded('catToolJobs')),
            'subProject' => SubProjectResource::make($this->whenLoaded('subProject')),
            'outsource_requests' => OutsourceRequestResource::collection($this->whenLoaded('outsourceRequests')),
            // Done in this way as we're expecting that in the future multiple PMs can be candidates for review tasks.
            'manager_candidates' => [
                ProjectManagerCandidateResource::make(
                    $this->when($this->jobDefinition?->job_key === JobKey::JOB_OVERVIEW, $this)
                )
            ],
            'can_download_xliff' => $subProject && Gate::forUser($request->user())->allows('downloadXliff', $subProject),
            'can_download_translations' => $subProject && Gate::forUser($request->user())->allows('downloadTranslations', $subProject),
        ];
    }
}
