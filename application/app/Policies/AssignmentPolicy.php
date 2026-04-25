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
        $project = $assignment->subProject->project;

        if ($user->isInSameInstitutionAs($project)) {
            if (Gate::allows('view', $project)) {
                return true;
            }

            return $user->isAssignedTo($assignment) || $this->isCandidateOf($user, $assignment);
        }

        return $user->hasPrivilege(PrivilegeKey::ViewExternalTranslationRequest) &&
            $user->isInPartnerInstitutionOfAssignment($assignment);
    }

    /**
     * Determine whether the user can create models.
     * partner access deliberately excluded
     */
    public function create(AuthUser $user, Assignment $assignment): bool
    {
        return Gate::allows('update', [$assignment->subProject->project]);
    }

    /**
     * Determine whether the user can update the model.
     * partner access deliberately excluded
     */
    public function update(AuthUser $user, Assignment $assignment): bool
    {
        return Gate::allows('update', [$assignment->subProject->project]);
    }

    /**
     * Determine whether the user can update the model.
     * partner access deliberately excluded
     */
    public function updateAssigneeComment(AuthUser $user, Assignment $assignment): bool
    {
        return $user->isInSameInstitutionAs($assignment->subProject->project) && (
                $user->hasPrivilege(PrivilegeKey::ManageProject) ||
                $this->isAssignedTo($user, $assignment)
            );
    }

    /**
     * Determine whether the user can delete the model.
     * partner access deliberately excluded
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
        $project = $assignment->subProject->project;

        if ($user->isInSameInstitutionAs($project)) {
            return $user->hasPrivilege(PrivilegeKey::ManageProject) ||
                $this->isAssignedTo($user, $assignment);
        }

        return $user->hasPrivilege(PrivilegeKey::ManageProject) &&
            $assignment->external_institution_id === $user->institutionId;
    }

    public function isCandidateOf(AuthUser $user, Assignment $assignment): bool
    {
        return $user->isVendor() && filled($vendor = $user->vendor()) &&
            $assignment->candidates()->where('vendor_id', $vendor->id)->exists();
    }

    public function isAssignedTo(AuthUser $user, Assignment $assignment): bool
    {
        return filled($assignment->assigned_vendor_id)
            && $user->isVendor()
            && $assignment->assigned_vendor_id === $user->vendor()?->id;
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
    public static function scope(): Scope\AssignmentScope
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
        $institutionId = Auth::user()->institutionId;
        $builder->where(function (Builder $outer) use ($institutionId) {
            $outer->whereHas('subProject.project',
                    fn (Builder $p) => $p->where('institution_id', $institutionId))
                ->orWhere(fn (Builder $self) => $self->sharedWithInstitution($institutionId));
        });
    }
}
