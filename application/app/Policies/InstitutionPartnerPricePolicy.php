<?php

namespace App\Policies;

use App\Enums\PrivilegeKey;
use App\Models\AuthUser;
use App\Models\InstitutionPartnerPrice;
use BadMethodCallException;

class InstitutionPartnerPricePolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return $user->hasPrivilege(PrivilegeKey::ViewExternalPartner);
    }

    public function view(AuthUser $user, InstitutionPartnerPrice $price): bool
    {
        throw new BadMethodCallException();
    }

    public function create(AuthUser $user, InstitutionPartnerPrice $price): bool
    {
        return $price->institutionPartner->institution_id == $user->institutionId
            && $user->hasPrivilege(PrivilegeKey::ManageExternalPartner);
    }

    public function update(AuthUser $user, InstitutionPartnerPrice $price): bool
    {
        return $price->institutionPartner->institution_id == $user->institutionId
            && $user->hasPrivilege(PrivilegeKey::ManageExternalPartner);
    }

    public function delete(AuthUser $user, InstitutionPartnerPrice $price): bool
    {
        return $price->institutionPartner->institution_id == $user->institutionId
            && $user->hasPrivilege(PrivilegeKey::ManageExternalPartner);
    }

    public function restore(AuthUser $user, InstitutionPartnerPrice $price): bool
    {
        throw new BadMethodCallException();
    }

    public function forceDelete(AuthUser $user, InstitutionPartnerPrice $price): bool
    {
        throw new BadMethodCallException();
    }

    public static function scope(): Scope\InstitutionPartnerPriceScope
    {
        return new Scope\InstitutionPartnerPriceScope();
    }
}

// Scope resides in the same file with Policy to enforce scope creation with policy creation.

namespace App\Policies\Scope;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope as IScope;
use Illuminate\Support\Facades\Auth;

class InstitutionPartnerPriceScope implements IScope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereRelation('institutionPartner', 'institution_id', Auth::user()->institutionId);
    }
}
