<?php

namespace App\Policies;

use App\Enums\PrivilegeKey;
use App\Models\AuthUser;
use App\Models\VendorSkillLanguage;
use BadMethodCallException;

class VendorSkillLanguagePolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return $user->hasAtLeastOnePrivilege([PrivilegeKey::ViewVendorDatabase, PrivilegeKey::ViewGeneralPricelist]);
    }

    public function view(AuthUser $user, VendorSkillLanguage $vendorSkillLanguage): bool
    {
        throw new BadMethodCallException();
    }

    public function create(AuthUser $user, VendorSkillLanguage $vendorSkillLanguage): bool
    {
        return $this->isInSameInstitutionAndCanEdit($user, $vendorSkillLanguage);
    }

    public function update(AuthUser $user, VendorSkillLanguage $vendorSkillLanguage): bool
    {
        return $this->isInSameInstitutionAndCanEdit($user, $vendorSkillLanguage);
    }

    public function delete(AuthUser $user, VendorSkillLanguage $vendorSkillLanguage): bool
    {
        return $this->isInSameInstitutionAndCanEdit($user, $vendorSkillLanguage);
    }

    public function restore(AuthUser $user, VendorSkillLanguage $vendorSkillLanguage): bool
    {
        throw new BadMethodCallException();
    }

    public function forceDelete(AuthUser $user, VendorSkillLanguage $vendorSkillLanguage): bool
    {
        throw new BadMethodCallException();
    }

    private function isInSameInstitutionAndCanEdit(AuthUser $user, VendorSkillLanguage $vendorSkillLanguage): bool
    {
        return $vendorSkillLanguage->vendor->institutionUser->institution['id'] == $user->institutionId
            && $user->hasPrivilege(PrivilegeKey::EditVendorDatabase);
    }

    public static function scope(): Scope\VendorSkillLanguageScope
    {
        return new Scope\VendorSkillLanguageScope();
    }
}

namespace App\Policies\Scope;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope as IScope;
use Illuminate\Support\Facades\Auth;

class VendorSkillLanguageScope implements IScope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereRelation('vendor.institutionUser', 'institution->id', Auth::user()->institutionId);
    }
}
