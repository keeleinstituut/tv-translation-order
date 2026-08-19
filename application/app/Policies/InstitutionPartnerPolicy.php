<?php

namespace App\Policies;

use App\Enums\PrivilegeKey;
use App\Models\AuthUser;
use App\Models\InstitutionPartner;
use BadMethodCallException;

class InstitutionPartnerPolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return $user->hasPrivilege(PrivilegeKey::ViewExternalPartner);
    }

    public function view(AuthUser $user, InstitutionPartner $partner): bool
    {
        throw new BadMethodCallException();
    }

    public function create(AuthUser $user, InstitutionPartner $partner): bool
    {
        return $partner->institution_id == $user->institutionId
            && $user->hasPrivilege(PrivilegeKey::ManageExternalPartner);
    }

    public function update(AuthUser $user, InstitutionPartner $partner): bool
    {
        return $partner->institution_id == $user->institutionId
            && $user->hasPrivilege(PrivilegeKey::ManageExternalPartner);
    }

    public function delete(AuthUser $user, InstitutionPartner $partner): bool
    {
        return $partner->institution_id == $user->institutionId
            && $user->hasPrivilege(PrivilegeKey::ManageExternalPartner);
    }

    public function restore(AuthUser $user, InstitutionPartner $partner): bool
    {
        throw new BadMethodCallException();
    }

    public function forceDelete(AuthUser $user, InstitutionPartner $partner): bool
    {
        throw new BadMethodCallException();
    }

    public static function scope(): Scope\InstitutionPartnerScope
    {
        return new Scope\InstitutionPartnerScope();
    }
}

// Scope resides in the same file with Policy to enforce scope creation with policy creation.

namespace App\Policies\Scope;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope as IScope;
use Illuminate\Support\Facades\Auth;

class InstitutionPartnerScope implements IScope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('institution_id', Auth::user()->institutionId);
    }
}
