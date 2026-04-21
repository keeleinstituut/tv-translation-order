<?php

namespace App\Policies;

use App\Enums\PrivilegeKey;
use App\Models\AuthUser;

class InstitutionMainLanguagePolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return $user->hasAtLeastOnePrivilege([
            PrivilegeKey::EditInstitution,
            PrivilegeKey::ReceiveProject,
            PrivilegeKey::ManageProject,
            PrivilegeKey::CreateProject
        ]) || $user->isVendor();
    }

    public function sync(AuthUser $user): bool
    {
        return $user->hasPrivilege(PrivilegeKey::EditInstitution);
    }

    public static function scope(): Scope\InstitutionMainLanguageScope
    {
        return new Scope\InstitutionMainLanguageScope();
    }
}

// Scope resides in the same file with Policy to enforce scope creation with policy creation.

namespace App\Policies\Scope;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope as IScope;
use Illuminate\Support\Facades\Auth;

class InstitutionMainLanguageScope implements IScope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('institution_id', Auth::user()->institutionId);
    }
}
