<?php

namespace App\Policies;

use App\Enums\PrivilegeKey;
use App\Models\Assignment;
use App\Models\Project;
use App\Models\SubProject;
use Illuminate\Support\Facades\Auth;
use KeycloakAuthGuard\Models\JwtPayloadUser;

class SubProjectPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(JwtPayloadUser $user, bool $onlyPersonalSubProjectsRequested): bool
    {
        return Auth::hasPrivilege(PrivilegeKey::ViewInstitutionProjectList->value)
            || $onlyPersonalSubProjectsRequested
            && Auth::hasPrivilege(PrivilegeKey::ViewPersonalProject->value);
    }

    /**
     * @return mixed
     *
     * TODO: add correct privilege check
     */
    public function viewAnyByTmKey(JwtPayloadUser $user)
    {
        return Auth::hasPrivilege(PrivilegeKey::ViewInstitutionProjectList->value);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(JwtPayloadUser $user, SubProject $subProject): bool
    {
        $currentInstitutionUserId = Auth::user()?->institutionUserId;

        if (empty($currentInstitutionUserId)) {
            return false;
        }

        $project = $subProject->project;
        if ($project->client_institution_user_id === $currentInstitutionUserId
            || $project->manager_institution_user_id === $currentInstitutionUserId) {
            return Auth::hasPrivilege(PrivilegeKey::ViewPersonalProject->value) ||
                Auth::hasPrivilege(PrivilegeKey::ViewInstitutionProjectDetail->value);
        }

        return Auth::hasPrivilege(PrivilegeKey::ViewInstitutionProjectDetail->value);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(JwtPayloadUser $user, SubProject $subProject): bool
    {
        return $this->hasManageProjectPrivilegeOrAssigned($subProject);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function manageCatTool(JwtPayloadUser $user, SubProject $subProject): bool
    {
        return $this->isInSameInstitutionAsCurrentUser($subProject) &&
            Auth::hasPrivilege(PrivilegeKey::ManageProject->value);
    }

    public function downloadXliff(JwtPayloadUser $user, SubProject $subProject): bool
    {
        return $this->hasManageProjectPrivilegeOrAssigned($subProject);
    }

    public function downloadTranslations(JwtPayloadUser $user, SubProject $subProject): bool
    {
        return $this->hasManageProjectPrivilegeOrAssigned($subProject);
    }

    public function editSourceFiles(JwtPayloadUser $user, SubProject $subProject): bool
    {
        return $this->hasManageProjectPrivilege($subProject);
    }

    public function editFinalFiles(JwtPayloadUser $user, SubProject $subProject): bool
    {
        return $this->hasManageProjectPrivilegeOrAssigned($subProject);
    }

    public function startWorkflow(JwtPayloadUser $user, SubProject $subProject): bool
    {
        return $this->hasManageProjectPrivilege($subProject);
    }

    public function markFilesAsProjectFinalFiles(JwtPayloadUser $user, SubProject $subProject): bool
    {
        return $this->hasManageProjectPrivilege($subProject);
    }

    private function hasManageProjectPrivilege(SubProject $subProject): bool
    {
        if (! $this->isInSameInstitutionAsCurrentUser($subProject)) {
            return false;
        }

        if (Auth::hasPrivilege(PrivilegeKey::ManageProject->value)) {
            return true;
        }
        return false;
    }

    private function hasManageProjectPrivilegeOrAssigned(SubProject $subProject): bool
    {
        if ($this->hasManageProjectPrivilege($subProject)) {
            return true;
        }

        return Assignment::where('assigned_vendor_id', Auth::user()->institutionUserId)
            ->where('sub_project_id', $subProject->id)->exists();
    }

    private function isInSameInstitutionAsCurrentUser(SubProject $subProject): bool
    {
        if (empty(Auth::user()?->institutionUserId)) {
            return false;
        }

        return filled($currentInstitutionId = Auth::user()?->institutionId)
            && $currentInstitutionId === $subProject->project->institution_id &&
            filled($currentInstitutionId = Auth::user()?->institutionUserId);
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
        $builder->whereRelation('project', function (Builder $query) {
            $query->where('institution_id', Auth::user()->institutionId);
        });
    }
}
