<?php

namespace App\Http\Resources\API;

use App\Enums\AssignmentStatus;
use App\Enums\CandidateStatus;
use App\Models\Assignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Assignment
 */
class ProjectManagerCandidateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        if (filled($manager = $this->subProject?->project?->managerInstitutionUser)) {
            $status = $this->status === AssignmentStatus::Done ?
                CandidateStatus::Done : CandidateStatus::Accepted;
        } else {
            $status = CandidateStatus::New;
        }

        return [
            'institution_user' => new InstitutionUserResource($manager),
            'status' => $status,
            'price' => null
        ];
    }
}
