<?php

namespace App\Policies;

use App\Enums\PrivilegeKey;
use App\Models\Assignment;
use App\Models\AuthUser;
use App\Models\SubProject;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class AssignmentPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(AuthUser $user, SubProject $subProject): bool
    {
        return Gate::allows('view', $subProject->project);

    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(AuthUser $user, Assignment $assignment): bool
    {
        if (Gate::allows('view', $assignment->subProject->project)) {
            return true;
        }

        if (!$user->isInSameInstitutionAs($assignment->subProject->project)) {
            return false;
        }

        return $user->isAssignedTo($assignment) || $user->isCandidateOf($assignment);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(AuthUser $user, Assignment $assignment): bool
    {
        return Gate::allows('update', [$assignment->subProject->project]);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(AuthUser $user, Assignment $assignment): bool
    {
        return Gate::allows('update', [$assignment->subProject->project]);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function updateAssigneeComment(AuthUser $user, Assignment $assignment): bool
    {
        return $user->isInSameInstitutionAs($assignment->subProject->project) && (
                $user->hasPrivilege(PrivilegeKey::ManageProject) ||
                $user->isAssignedTo($assignment)
            );
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(AuthUser $user, Assignment $assignment): bool
    {
        return $user->hasPrivilege(PrivilegeKey::ManageProject);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(AuthUser $user, Assignment $assignment): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(AuthUser $user, Assignment $assignment): bool
    {
        return false;
    }

    public function markAsCompleted(AuthUser $user, Assignment $assignment): bool
    {
        return $user->isInSameInstitutionAs($assignment->subProject->project) && (
                $user->hasPrivilege(PrivilegeKey::ManageProject) ||
                $user->isAssignedTo($assignment)
            );
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
        return new Scope\AssignmentScope();
    }
}

// Scope resides in the same file with Policy to enforce scope creation with policy creation.

namespace App\Policies\Scope;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope as IScope;
use Illuminate\Support\Facades\Auth;

class AssignmentScope implements IScope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereHas('subProject', function (Builder $subProjectQuery) {
            $subProjectQuery->whereHas('project', function (Builder $projectQuery) {
                $projectQuery->where('institution_id', Auth::user()->institutionId);
            });
        });
    }
}
