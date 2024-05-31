<?php

namespace App\Policies;


use App\Enums\PrivilegeKey;
use App\Models\Project;
use App\Models\ProjectReviewRejection;
use Illuminate\Support\Facades\Auth;
use KeycloakAuthGuard\Models\JwtPayloadUser;

class ProjectReviewRejectionPolicy
{
    public function downloadMedia(JwtPayloadUser $user, ProjectReviewRejection $projectReview): bool
    {
        if (empty($project = $projectReview->project)) {
            return false;
        }

        if (! $this->isInSameInstitutionAsCurrentUser($project)) {
            return false;
        }

        return Auth::hasPrivilege(PrivilegeKey::ManageProject->value) ||
            Auth::hasPrivilege(PrivilegeKey::ViewInstitutionProjectDetail->value) ||
            $this->currentUserIsClient($project);
    }

    public static function isInSameInstitutionAsCurrentUser(Project $project): bool
    {
        return filled($currentInstitutionId = Auth::user()?->institutionId)
            && $currentInstitutionId === $project->institution_id;
    }

    private function currentUserIsClient(Project $project): bool
    {
        if (empty($institutionUserId = Auth::user()?->institutionUserId)) {
            return false;
        }

        return $project->client_institution_user_id === $institutionUserId;
    }
}
