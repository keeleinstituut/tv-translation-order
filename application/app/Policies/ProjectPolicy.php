<?php

namespace App\Policies;

use App\Enums\PrivilegeKey;
use App\Enums\ProjectStatus;
use App\Models\AuthUser;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;

class ProjectPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(AuthUser $user, bool $onlyPersonalProjectsRequested, bool $onlyUnclaimedProjectsRequested): bool
    {
        return $user->hasAtLeastOnePrivilege([PrivilegeKey::ViewInstitutionProjectList, PrivilegeKey::ViewInstitutionProjectDetail]) ||
            ($onlyUnclaimedProjectsRequested && $user->hasPrivilege(PrivilegeKey::ViewInstitutionUnclaimedProjectDetail)) ||
            ($onlyPersonalProjectsRequested && $user->hasPrivilege(PrivilegeKey::ViewPersonalProject));
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(AuthUser $user, Project $project): bool
    {
        if (empty($user->institutionUserId)) {
            return false;
        }

        if ($user->hasPrivilege(PrivilegeKey::ViewInstitutionProjectDetail)) {
            return true;
        }

        if ($project->status === ProjectStatus::New && $user->hasPrivilege(PrivilegeKey::ViewInstitutionUnclaimedProjectDetail)) {
            return true;
        }

        if ($user->isClientOf($project)
            || $user->isManagerOf($project)) {
            return $user->hasPrivilege(PrivilegeKey::ViewPersonalProject);
        }

        if ($project->is_calendar_project) {
            if ($vendor = $user->vendor()) {
                return $project->calendarEntries()->where('vendor_id', $vendor->id)->exists();
            }
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(AuthUser $user, Project $project): bool
    {
        if (empty($user->institutionUserId)) {
            return false;
        }

        return $user->hasPrivilege(PrivilegeKey::CreateProject) &&
            ($user->isClientOf($project) || $user->hasPrivilege(PrivilegeKey::ChangeClient)) &&
            ($project->manager_institution_user_id === null || $user->isManagerOf($project) ||
                $user->hasPrivilege(PrivilegeKey::ChangeProjectManager)
            );
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(AuthUser $user, Project $project): bool
    {
        return $user->isInSameInstitutionAs($project) &&
            $user->hasPrivilege(PrivilegeKey::ManageProject);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function changeClient(AuthUser $user, Project $project): bool
    {
        return $user->isInSameInstitutionAs($project) &&
            ($user->hasPrivilege(PrivilegeKey::ChangeClient) || empty($project->client_institution_user_id));
    }

    public function changeProjectManager(AuthUser $user, Project $project): bool
    {
        return $user->isInSameInstitutionAs($project) &&
            ($user->hasPrivilege(PrivilegeKey::ChangeProjectManager) || empty($project->manager_institution_user_id));
    }

    public function editSourceFiles(AuthUser $user, Project $project): bool
    {
        return $user->isInSameInstitutionAs($project) && (
                $user->hasPrivilege(PrivilegeKey::ManageProject) ||
                $user->isClientOf($project)
            );
    }

    public function editHelpFiles(AuthUser $user, Project $project): bool
    {
        return $user->isInSameInstitutionAs($project) && (
                $user->hasPrivilege(PrivilegeKey::ManageProject) ||
                $user->isClientOf($project)
            );
    }

    public function downloadMedia(AuthUser $user, Project $project): bool
    {
        if (! $user->isInSameInstitutionAs($project)) {
            return false;
        }

        return $user->hasPrivilege(PrivilegeKey::ManageProject) ||
            $user->isClientOf($project) ||
            $user->isAssignmentsCandidate($project);
    }

    public function cancel(AuthUser $user, Project $project): bool
    {
        return $user->hasPrivilege(PrivilegeKey::ManageProject) || $user->isClientOf($project);
    }

    public function review(AuthUser $user, Project $project): bool
    {
        return $user->isClientOf($project);
    }

    public function export(AuthUser $user)
    {
        return $user->hasPrivilege(PrivilegeKey::ExportInstitutionGeneralReport);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(AuthUser $user, Project $project): bool
    {
        return false; // TODO
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(AuthUser $user, Project $project): bool
    {
        return false; // TODO
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(AuthUser $user, Project $project): bool
    {
        return false; // TODO
    }

    // Should serve as an query enhancement to Eloquent queries
    // to filter out objects that the user does not have permissions to.
    //
    // Example usage in query:
    // Role::getModel()->withGlobalScope('policy', RolePolicy::scope())->get();
    //
    // The 'policy' string in the example is not strict and is used internally to identify
    // the scope applied in Eloquent querybuilder. It can be something else as well,
    // but it should correspond with the intentions of the scope. Using 'policy' provides
    // general understanding throughout the whole project that the applied scope is related to policy.
    // The withGlobalScope method does not apply the scope globally, it applies to only the querybuilder
    // of current query. The method name could be different, but in the sake of reusability
    // we can use this method that's provided by Laravel and used internally.
    //
    public static function scope()
    {
        return new Scope\ProjectScope();
    }
}

// Scope resides in the same file with Policy to enforce scope creation with policy creation.

namespace App\Policies\Scope;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope as IScope;
use Illuminate\Support\Facades\Auth;

class ProjectScope implements IScope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('institution_id', Auth::user()->institutionId);
    }
}
