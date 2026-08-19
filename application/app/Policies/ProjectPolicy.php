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
     * partner access deliberately excluded
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

        if ($user->isClientOfProject($project)
            || $user->isManagerOfProject($project)) {
            return $user->hasPrivilege(PrivilegeKey::ViewPersonalProject);
        }

        if ($project->is_calendar_project) {
            if ($vendor = $user->vendor()) {
                return $project->calendarEntries()->where('vendor_id', $vendor->id)->exists();
            }
        }

        if ($user->hasPrivilege(PrivilegeKey::ViewOutsourceRequest)) {
            return $user->hasActivePartnerAccessToProject($project);
        }

        return false;
    }

    /**
     * Determine whether the user can create models.
     * partner access deliberately excluded
     */
    public function create(AuthUser $user, Project $project): bool
    {
        if (empty($user->institutionUserId)) {
            return false;
        }

        if ($user->belongsToTranslationAgency()) {
            return false;
        }

        return $user->hasPrivilege(PrivilegeKey::CreateProject) &&
            ($user->isClientOfProject($project) || $user->hasPrivilege(PrivilegeKey::ChangeClient)) &&
            ($project->manager_institution_user_id === null || $user->isManagerOfProject($project) ||
                $user->hasPrivilege(PrivilegeKey::ChangeProjectManager)
            );
    }

    /**
     * Determine whether the user can update the model.
     * partner access deliberately excluded
     */
    public function update(AuthUser $user, Project $project): bool
    {
        return $user->isInSameInstitutionAsProject($project) &&
            $user->hasPrivilege(PrivilegeKey::ManageProject);
    }

    /**
     * Determine whether the user can update the model.
     * partner access deliberately excluded
     */
    public function changeClient(AuthUser $user, Project $project): bool
    {
        if ($user->belongsToTranslationAgency()) {
            return false;
        }

        return $user->isInSameInstitutionAsProject($project) &&
            ($user->hasPrivilege(PrivilegeKey::ChangeClient) || empty($project->client_institution_user_id));
    }

    // partner access deliberately excluded
    public function changeProjectManager(AuthUser $user, Project $project): bool
    {
        if ($user->belongsToTranslationAgency()) {
            return false;
        }

        return $user->isInSameInstitutionAsProject($project) &&
            ($user->hasPrivilege(PrivilegeKey::ChangeProjectManager) || empty($project->manager_institution_user_id));
    }

    // partner access deliberately excluded
    public function editSourceFiles(AuthUser $user, Project $project): bool
    {
        if ($user->belongsToTranslationAgency()) {
            return false;
        }

        return $user->isInSameInstitutionAsProject($project) && (
                $user->hasPrivilege(PrivilegeKey::ManageProject) ||
                $user->isClientOfProject($project)
            );
    }

    // partner access deliberately excluded
    public function editHelpFiles(AuthUser $user, Project $project): bool
    {
        if ($user->belongsToTranslationAgency()) {
            return false;
        }

        return $user->isInSameInstitutionAsProject($project) && (
                $user->hasPrivilege(PrivilegeKey::ManageProject) ||
                $user->isClientOfProject($project)
            );
    }

    public function downloadMedia(AuthUser $user, Project $project): bool
    {
        if ($user->hasPrivilege(PrivilegeKey::ViewOutsourceRequest) &&
            (
                $user->hasActivePartnerAccessToProject($project)
                || $user->hasSharedPartnerAccessToProject($project, true)
            )) {
            return true;
        }

        if (! $user->isInSameInstitutionAsProject($project)) {
            return false;
        }

        return $user->hasPrivilege(PrivilegeKey::ManageProject) ||
            $user->isClientOfProject($project) ||
            $user->hasAssignmentCandidateAccessToProject($project);
    }

    // partner access deliberately excluded
    public function cancel(AuthUser $user, Project $project): bool
    {
        if ($user->belongsToTranslationAgency()) {
            return false;
        }

        return $user->hasPrivilege(PrivilegeKey::ManageProject) || $user->isClientOfProject($project);
    }

    // partner access deliberately excluded
    public function review(AuthUser $user, Project $project): bool
    {
        if ($user->belongsToTranslationAgency()) {
            return false;
        }

        return $user->isClientOfProject($project);
    }

    // partner access deliberately excluded
    public function export(AuthUser $user): bool
    {
        if ($user->belongsToTranslationAgency()) {
            return false;
        }

        return $user->hasPrivilege(PrivilegeKey::ExportInstitutionGeneralReport);
    }

    /**
     * Determine whether the user can delete the model.
     * partner access deliberately excluded
     */
    public function delete(AuthUser $user, Project $project): bool
    {
        return false; // TODO
    }

    /**
     * Determine whether the user can restore the model.
     * partner access deliberately excluded
     */
    public function restore(AuthUser $user, Project $project): bool
    {
        return false; // TODO
    }

    /**
     * Determine whether the user can permanently delete the model.
     * partner access deliberately excluded
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
    public static function scope(): Scope\ProjectScope
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
        $institutionId = Auth::user()->institutionId;
        $builder->where(function (Builder $q) use ($institutionId) {
            $q->where('institution_id', $institutionId)
                ->orWhereHas('subProjects.assignments',
                    fn (Builder $a) => $a->sharedWithInstitution($institutionId));
        });
    }
}
