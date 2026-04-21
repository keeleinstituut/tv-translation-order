<?php

namespace App\Policies;

use App\Enums\PrivilegeKey;
use App\Models\AuthUser;
use App\Models\InstitutionUserPinnedLanguage;

class InstitutionUserPinnedLanguagePolicy
{
    public function create(AuthUser $user): bool
    {
        return $user->hasAtLeastOnePrivilege([PrivilegeKey::ManageProject, PrivilegeKey::CreateProject]);
    }

    public function delete(AuthUser $user, InstitutionUserPinnedLanguage $pinnedLanguage): bool
    {
        return $pinnedLanguage->institution_user_id === $user->institutionUserId;
    }

    public static function scope(): Scope\InstitutionUserPinnedLanguageScope
    {
        return new Scope\InstitutionUserPinnedLanguageScope();
    }
}

// Scope resides in the same file with Policy to enforce scope creation with policy creation.

namespace App\Policies\Scope;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope as IScope;
use Illuminate\Support\Facades\Auth;

class InstitutionUserPinnedLanguageScope implements IScope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('institution_user_id', Auth::user()->institutionUserId);
    }
}
