<?php

namespace App\Http\Resources\API;

use App\Models\Assignment;
use App\Models\Candidate;
use App\Models\CatToolJob;
use App\Models\SubProject;
use App\Models\Vendor;
use App\Models\Volume;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * @mixin Assignment
 */
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
                'sub_project_id',
                'ext_id',
                'deadline_at',
                'comments',
                'assignee_comments',
                'feature',
                'created_at',
                'updated_at',
            ),
            'assignee' => VendorResource::make($this->assignee),
            'candidates' => VendorResource::collection($this->candidates),
            'volumes' => VolumeResource::collection($this->volumes),
            'catToolJobs' => CatToolJobResource::collection($this->catToolJobs)
        ];
    }
}
