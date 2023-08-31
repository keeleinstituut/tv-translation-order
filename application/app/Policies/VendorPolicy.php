<?php

namespace App\Policies;

use App\Models\Vendor;
use BadMethodCallException;
use Illuminate\Support\Facades\Auth;
use KeycloakAuthGuard\Models\JwtPayloadUser;

class VendorPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(JwtPayloadUser $jwtPayloadUser): bool
    {
        return Auth::hasPrivilege('VIEW_VENDOR_DB');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(JwtPayloadUser $jwtPayloadUser, Vendor $vendor): bool
    {
        return Auth::hasPrivilege('VIEW_VENDOR_DB');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(JwtPayloadUser $jwtPayloadUser, Vendor $vendor): bool
    {
        return $vendor
            && $vendor->institutionUser->institution['id'] == $jwtPayloadUser->institutionId
            && Auth::hasPrivilege('EDIT_VENDOR_DB');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(JwtPayloadUser $jwtPayloadUser, Vendor $vendor): bool
    {
        return $vendor
            && $vendor->institutionUser->institution['id'] == $jwtPayloadUser->institutionId
            && Auth::hasPrivilege('EDIT_VENDOR_DB');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(JwtPayloadUser $jwtPayloadUser, Vendor $vendor): bool
    {
        return $vendor
            && $vendor->institutionUser->institution['id'] == $jwtPayloadUser->institutionId
            && Auth::hasPrivilege('EDIT_VENDOR_DB');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(JwtPayloadUser $jwtPayloadUser, Vendor $vendor): bool
    {
        throw new BadMethodCallException();
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(JwtPayloadUser $jwtPayloadUser, Vendor $vendor): bool
    {
        throw new BadMethodCallException();
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
        return new Scope\VendorScope();
    }
}

// Scope resides in the same file with Policy to enforce scope creation with policy creation.

namespace App\Policies\Scope;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope as IScope;
use Illuminate\Support\Facades\Auth;

class VendorScope implements IScope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereRelation('institutionUser', 'institution->id', Auth::user()->institutionId);
    }
}
