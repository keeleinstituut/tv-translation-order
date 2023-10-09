<?php

namespace App\Http\Resources\API;

use App\Models\Assignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
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
        'feature',
        'created_at',
        'updated_at',
    ],
    properties: [
        new OA\Property(property: 'id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'sub_project_id', type: 'string', format: 'uuid'),
        new OA\Property(property: 'ext_id', type: 'string'),
        new OA\Property(property: 'deadline_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'comments', type: 'string'),
        new OA\Property(property: 'assignee_comments', type: 'string'),
        new OA\Property(property: 'feature', type: 'string'),
        new OA\Property(property: 'created_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'updated_at', type: 'string', format: 'date-time'),
        new OA\Property(property: 'assignee', ref: VendorResource::class),
        new OA\Property(property: 'candidates', type: 'array', items: new OA\Items(ref: VendorResource::class)),
        new OA\Property(property: 'volumes', type: 'array', items: new OA\Items(ref: VolumeResource::class)),
        new OA\Property(property: 'jobs', type: 'array', items: new OA\Items(ref: CatToolJobResource::class)),
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
        return [
            ...$this->only(
                'id',
                'sub_project_id',
                'ext_id',
                'deadline_at',
                'comments',
                'assignee_comments',
                'feature',
                'created_at',
                'updated_at',
            ),
            'assignee' => VendorResource::make($this->whenLoaded('assignee')),
            'candidates' => CandidateResource::collection($this->whenLoaded('candidates')),
            'volumes' => VolumeResource::collection($this->whenLoaded('volumes')),
            'jobs' => CatToolJobResource::collection($this->whenLoaded('catToolJobs')),
        ];
    }
}
