<?php

namespace App\Policies;

use App\Models\CatToolTmKey;
use App\Models\SubProject;
use Illuminate\Support\Facades\Gate;
use KeycloakAuthGuard\Models\JwtPayloadUser;

class CatToolTmKeyPolicy
{
    /**
     * Determine whether the user can view any models.
     * TODO: set correct permission check
     */
    public function viewAny(JwtPayloadUser $user, SubProject $subProject): bool
    {
        return Gate::allows('update', [$subProject->project]);
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(JwtPayloadUser $user, CatToolTmKey $catToolTm): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(JwtPayloadUser $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can create models.
     * TODO: set correct permission check
     */
    public function sync(JwtPayloadUser $user, SubProject $subProject): bool
    {
        return Gate::allows('update', [$subProject->project]);
    }

    /**
     * @return bool
     *
     * TODO: set correct permission check
     */
    public function toggleWritable(JwtPayloadUser $user, CatToolTmKey $tmKey): bool
    {
        return Gate::allows('update', [$tmKey->subProject->project]);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(JwtPayloadUser $user, CatToolTmKey $catToolTm): bool
    {
        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(JwtPayloadUser $user, CatToolTmKey $catToolTm): bool
    {
        return false;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(JwtPayloadUser $user, CatToolTmKey $catToolTm): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(JwtPayloadUser $user, CatToolTmKey $catToolTm): bool
    {
        return false;
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
    public static function scope(): Scope\CatToolTmScope
    {
        return new Scope\CatToolTmScope();
    }
}

// Scope resides in the same file with Policy to enforce scope creation with policy creation.

namespace App\Policies\Scope;

use App\Policies\SubProjectPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope as IScope;

class CatToolTmScope implements IScope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereHas('subProject', function (Builder $subProjectQuery) {
            $subProjectQuery->withGlobalScope('policy', SubProjectPolicy::scope());
        });
    }
}
