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

        if (! ProjectPolicy::isInSameInstitutionAsCurrentUser($project)) {
            return false;
        }

        return Auth::hasPrivilege(PrivilegeKey::ManageProject->value) ||
            Auth::hasPrivilege(PrivilegeKey::ViewInstitutionProjectDetail->value) ||
            ProjectPolicy::isClient($project) ||
            ProjectPolicy::isAssignmentCandidate($project);
    }
}
