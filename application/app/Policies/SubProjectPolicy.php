<?php

namespace App\Policies;

use App\Enums\PrivilegeKey;
use App\Models\Assignment;
use App\Models\AuthUser;
use App\Models\SubProject;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class SubProjectPolicy
{
    /**
     * Determine whether the user can view any models.
     * partner access deliberately excluded
     */
    public function viewAny(AuthUser $user, bool $onlyPersonalSubProjectsRequested): bool
    {
        return $user->hasAtLeastOnePrivilege([PrivilegeKey::ViewInstitutionProjectList, PrivilegeKey::ViewInstitutionProjectDetail]) ||
            ($onlyPersonalSubProjectsRequested && $user->hasPrivilege(PrivilegeKey::ViewPersonalProject));
    }

    /**
     * @return mixed
     *
     * TODO: add correct privilege check
     * partner access deliberately excluded
     */
    public function viewAnyByTmKey(AuthUser $user)
    {
        return $user->hasAtLeastOnePrivilege([PrivilegeKey::ViewInstitutionProjectList, PrivilegeKey::ViewInstitutionProjectDetail]);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(AuthUser $user, SubProject $subProject): bool
    {
        if (empty($user->institutionUserId)) {
            return false;
        }

        $project = $subProject->project;
        if ($user->isClientOfProject($project)
            || $user->isManagerOfProject($project)) {
            return $user->hasAtLeastOnePrivilege([PrivilegeKey::ViewPersonalProject, PrivilegeKey::ViewInstitutionProjectDetail]);
        }

        if ($user->hasPrivilege(PrivilegeKey::ViewInstitutionProjectDetail) && $user->isInSameInstitutionAsProject($project)) {
            return true;
        }

        if ($user->hasPrivilege(PrivilegeKey::ViewOutsourceRequest)) {
            return $user->hasActivePartnerAccessToSubProject($subProject);
        }

        return false;
    }

    /**
     * Determine whether the user can update the model.
     * partner access deliberately excluded
     */
    public function update(AuthUser $user, SubProject $subProject): bool
    {
        return $this->hasManageProjectPrivilegeOrAssigned($user, $subProject);
    }

    /**
     * Determine whether the user can update the model.
     * partner access deliberately excluded
     */
    public function manageCatTool(AuthUser $user, SubProject $subProject): bool
    {
        return $user->isInSameInstitutionAsProject($subProject->project) &&
            $user->hasPrivilege(PrivilegeKey::ManageProject);
    }

    // partner access deliberately excluded
    public function downloadXliff(AuthUser $user, SubProject $subProject): bool
    {
        return $this->hasManageProjectPrivilegeOrAssigned($user, $subProject);
    }

    // partner access deliberately excluded
    public function downloadTranslations(AuthUser $user, SubProject $subProject): bool
    {
        return $this->hasManageProjectPrivilegeOrAssigned($user, $subProject);
    }

    public function downloadMedia(AuthUser $user, SubProject $subProject): bool
    {
        if ($user->hasPrivilege(PrivilegeKey::ViewOutsourceRequest) &&
            (
                $user->hasActivePartnerAccessToSubProject($subProject)
                || $user->hasSharedPartnerAccessToSubProject($subProject, true)
            )) {
            return true;
        }

        return $this->hasManageProjectPrivilegeOrAssigned($user, $subProject) ||
            $user->isClientOfProject($subProject->project);
    }

    // partner access deliberately excluded
    public function editSourceFiles(AuthUser $user, SubProject $subProject): bool
    {
        return $this->hasManageProjectPrivilege($user, $subProject) ||
            $user->isClientOfProject($subProject->project);
    }

    // partner access deliberately excluded
    public function editFinalFiles(AuthUser $user, SubProject $subProject, ?string $assignmentId = null): bool
    {
        return $this->hasManageProjectPrivilegeOrAssigned($user, $subProject, $assignmentId);
    }

    // partner access deliberately excluded
    public function startWorkflow(AuthUser $user, SubProject $subProject): bool
    {
        return $this->hasManageProjectPrivilege($user, $subProject);
    }

    // partner access deliberately excluded
    public function markFilesAsProjectFinalFiles(AuthUser $user, SubProject $subProject): bool
    {
        return $this->hasManageProjectPrivilege($user, $subProject);
    }

    private function hasManageProjectPrivilege(AuthUser $user, SubProject $subProject): bool
    {
        if (! $user->isInSameInstitutionAsProject($subProject->project)) {
            return false;
        }

        return $user->hasPrivilege(PrivilegeKey::ManageProject);
    }

    private function hasManageProjectPrivilegeOrAssigned(AuthUser $user, SubProject $subProject, ?string $assignmentId = null): bool
    {
        if ($this->hasManageProjectPrivilege($user, $subProject)) {
            return true;
        }

        $vendor = $user->vendor();

        if (empty($vendor)) {
            return false;
        }

        return Assignment::where('assigned_vendor_id', $vendor->id)
            ->where('sub_project_id', $subProject->id)
            ->when(filled($assignmentId), fn (Builder $query) => $query->where('id', $assignmentId))
            ->exists();
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
    public static function scope(): Scope\SubProjectScope
    {
        return new Scope\SubProjectScope();
    }
}

// Scope resides in the same file with Policy to enforce scope creation with policy creation.

namespace App\Policies\Scope;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope as IScope;
use Illuminate\Support\Facades\Auth;

class SubProjectScope implements IScope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $institutionId = Auth::user()->institutionId;
        $builder->where(function (Builder $outer) use ($institutionId) {
            $outer->whereRelation('project', fn (Builder $p) => $p->where('institution_id', $institutionId))
                ->orWhereHas('assignments', fn (Builder $a) => $a->sharedWithInstitution($institutionId));
        });
    }
}
