<?php

namespace App\Policies;

use App\Enums\PrivilegeKey;
use App\Models\AuthUser;
use App\Models\VendorSkillLanguagePair;
use BadMethodCallException;

class VendorSkillLanguagePairPolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return $user->hasAtLeastOnePrivilege([PrivilegeKey::ViewVendorDatabase, PrivilegeKey::ViewGeneralPricelist]);
    }

    public function view(AuthUser $user, VendorSkillLanguagePair $pair): bool
    {
        throw new BadMethodCallException();
    }

    public function create(AuthUser $user, VendorSkillLanguagePair $pair): bool
    {
        return $this->isInSameInstitutionAsCurrentUserAndHasEditVendorDbPrivilege($user, $pair);
    }

    public function update(AuthUser $user, VendorSkillLanguagePair $pair): bool
    {
        return $this->isInSameInstitutionAsCurrentUserAndHasEditVendorDbPrivilege($user, $pair);
    }

    public function delete(AuthUser $user, VendorSkillLanguagePair $pair): bool
    {
        return $this->isInSameInstitutionAsCurrentUserAndHasEditVendorDbPrivilege($user, $pair);
    }

    public function restore(AuthUser $user, VendorSkillLanguagePair $pair): bool
    {
        throw new BadMethodCallException();
    }

    public function forceDelete(AuthUser $user, VendorSkillLanguagePair $pair): bool
    {
        throw new BadMethodCallException();
    }

    private function isInSameInstitutionAsCurrentUserAndHasEditVendorDbPrivilege(AuthUser $user, VendorSkillLanguagePair $pair): bool
    {
        return $pair
            && $pair->vendor->institutionUser->institution['id'] == $user->institutionId
            && $user->hasPrivilege(PrivilegeKey::EditVendorDatabase);
    }

    public static function scope(): Scope\VendorSkillLanguagePairScope
    {
        return new Scope\VendorSkillLanguagePairScope();
    }
}

// Scope resides in the same file with Policy to enforce scope creation with policy creation.

namespace App\Policies\Scope;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope as IScope;
use Illuminate\Support\Facades\Auth;

class VendorSkillLanguagePairScope implements IScope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereRelation('vendor.institutionUser', 'institution->id', Auth::user()->institutionId);
    }
}
