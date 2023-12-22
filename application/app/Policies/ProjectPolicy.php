<?php

namespace App\Policies;

use App\Enums\PrivilegeKey;
use App\Models\Project;
use Illuminate\Support\Facades\Auth;
use KeycloakAuthGuard\Models\JwtPayloadUser;

class ProjectPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(JwtPayloadUser $user, bool $onlyPersonalProjectsRequested): bool
    {
        return Auth::hasPrivilege(PrivilegeKey::ViewInstitutionProjectList->value) ||
            Auth::hasPrivilege(PrivilegeKey::ViewInstitutionProjectDetail->value) ||
            ($onlyPersonalProjectsRequested && Auth::hasPrivilege(PrivilegeKey::ViewPersonalProject->value));
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(JwtPayloadUser $user, Project $project): bool
    {
        $currentInstitutionUserId = Auth::user()?->institutionUserId;

        if (empty($currentInstitutionUserId)) {
            return false;
        }

        if ($project->client_institution_user_id === $currentInstitutionUserId
            || $project->manager_institution_user_id === $currentInstitutionUserId) {
            return Auth::hasPrivilege(PrivilegeKey::ViewPersonalProject->value) ||
                Auth::hasPrivilege(PrivilegeKey::ViewInstitutionProjectDetail->value);
        }

        return Auth::hasPrivilege(PrivilegeKey::ViewInstitutionProjectDetail->value);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(JwtPayloadUser $user, Project $project): bool
    {
        $currentInstitutionUserId = Auth::user()?->institutionUserId;

        if (empty($currentInstitutionUserId)) {
            return false;
        }

        return Auth::hasPrivilege(PrivilegeKey::CreateProject->value)
            && (
                $project->client_institution_user_id === $currentInstitutionUserId
                || Auth::hasPrivilege(PrivilegeKey::ChangeClient->value)
            );
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(JwtPayloadUser $user, Project $project): bool
    {
        return $this->isInSameInstitutionAsCurrentUser($project) &&
            Auth::hasPrivilege(PrivilegeKey::ManageProject->value);
    }

    public function editSourceFiles(JwtPayloadUser $user, Project $project): bool
    {
        return $this->isInSameInstitutionAsCurrentUser($project) &&
            Auth::hasPrivilege(PrivilegeKey::ManageProject->value);
    }

    public function editHelpFiles(JwtPayloadUser $user, Project $project): bool
    {
        return $this->isInSameInstitutionAsCurrentUser($project) &&
            Auth::hasPrivilege(PrivilegeKey::ManageProject->value);
    }

    public function downloadMedia(JwtPayloadUser $user, Project $project): bool
    {
        return $this->isInSameInstitutionAsCurrentUser($project) &&
            Auth::hasPrivilege(PrivilegeKey::ManageProject->value);
    }

    public function cancel(JwtPayloadUser $user, Project $project): bool
    {
        $currentInstitutionUserId = Auth::user()?->institutionUserId;

        if (empty($currentInstitutionUserId)) {
            return false;
        }

        return Auth::hasPrivilege(PrivilegeKey::ManageProject->value) ||
            $project->client_institution_user_id === $currentInstitutionUserId;
    }

    public function review(JwtPayloadUser $user, Project $project): bool
    {
        $currentInstitutionUserId = Auth::user()?->institutionUserId;
        return $currentInstitutionUserId === $project->client_institution_user_id;
    }

    public function export(JwtPayloadUser $user)
    {
        return Auth::hasPrivilege(PrivilegeKey::ExportInstitutionGeneralReport->value);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(JwtPayloadUser $user, Project $project): bool
    {
        return false; // TODO
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(JwtPayloadUser $user, Project $project): bool
    {
        return false; // TODO
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(JwtPayloadUser $user, Project $project): bool
    {
        return false; // TODO
    }

    public static function isInSameInstitutionAsCurrentUser(Project $project): bool
    {
        return filled($currentInstitutionId = Auth::user()?->institutionId)
            && $currentInstitutionId === $project->institution_id;
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
