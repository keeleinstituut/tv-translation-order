<?php

namespace App\Policies;

use App\Enums\PrivilegeKey;
use App\Models\AuthUser;
use App\Models\InstitutionPrice;
use BadMethodCallException;

class InstitutionPricePolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return $user->hasPrivilege(PrivilegeKey::ViewInstitutionPricelist);
    }

    public function view(AuthUser $user, InstitutionPrice $price): bool
    {
        throw new BadMethodCallException();
    }

    public function create(AuthUser $user, InstitutionPrice $price): bool
    {
        return $price->institution_id == $user->institutionId
            && $user->hasPrivilege(PrivilegeKey::EditInstitutionPricelist);
    }

    public function update(AuthUser $user, InstitutionPrice $price): bool
    {
        return $price->institution_id == $user->institutionId
            && $user->hasPrivilege(PrivilegeKey::EditInstitutionPricelist);
    }

    public function delete(AuthUser $user, InstitutionPrice $price): bool
    {
        return $price->institution_id == $user->institutionId
            && $user->hasPrivilege(PrivilegeKey::EditInstitutionPricelist);
    }

    public function restore(AuthUser $user, InstitutionPrice $price): bool
    {
        throw new BadMethodCallException();
    }

    public function forceDelete(AuthUser $user, InstitutionPrice $price): bool
    {
        throw new BadMethodCallException();
    }

    public static function scope(): Scope\InstitutionPriceScope
    {
        return new Scope\InstitutionPriceScope();
    }
}

// Scope resides in the same file with Policy to enforce scope creation with policy creation.

namespace App\Policies\Scope;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope as IScope;
use Illuminate\Support\Facades\Auth;

class InstitutionPriceScope implements IScope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('institution_id', Auth::user()->institutionId);
    }
}
