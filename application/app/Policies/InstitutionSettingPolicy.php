<?php

namespace App\Policies;

use App\Enums\PrivilegeKey;
use App\Models\AuthUser;

class InstitutionSettingPolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return true;
    }

    public function update(AuthUser $user): bool
    {
        return $user->hasPrivilege(PrivilegeKey::EditInstitution);
    }

    public static function scope(): Scope\InstitutionSettingScope
    {
        return new Scope\InstitutionSettingScope();
    }
}

// Scope resides in the same file with Policy to enforce scope creation with policy creation.

namespace App\Policies\Scope;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope as IScope;
use Illuminate\Support\Facades\Auth;

class InstitutionSettingScope implements IScope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('institution_id', Auth::user()->institutionId);
    }
}
