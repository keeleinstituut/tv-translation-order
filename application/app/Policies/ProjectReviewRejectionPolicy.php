<?php

namespace App\Policies;

use App\Enums\PrivilegeKey;
use App\Models\AuthUser;
use App\Models\ProjectReviewRejection;

class ProjectReviewRejectionPolicy
{
    public function downloadMedia(AuthUser $user, ProjectReviewRejection $projectReview): bool
    {
        if (empty($project = $projectReview->project)) {
            return false;
        }

        if (! $user->isInSameInstitutionAs($project)) {
            return false;
        }

        return $user->hasAtLeastOnePrivilege([PrivilegeKey::ManageProject, PrivilegeKey::ViewInstitutionProjectDetail]) ||
            $user->isClientOf($project) ||
            $user->isAssignmentsCandidate($project);
    }
}
