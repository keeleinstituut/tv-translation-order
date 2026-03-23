<?php

namespace App\Policies;

use App\Enums\PrivilegeKey;
use App\Models\ProjectComment;
use Illuminate\Support\Facades\Auth;
use KeycloakAuthGuard\Models\JwtPayloadUser;

class ProjectCommentPolicy
{
    public function viewAny(JwtPayloadUser $jwtPayloadUser): bool
    {
        return Auth::hasPrivilege(PrivilegeKey::ViewInstitutionProjectDetail->value)
            || Auth::hasPrivilege(PrivilegeKey::ManageProject->value)
            || Auth::hasPrivilege(PrivilegeKey::ViewPersonalProject->value);
    }

    public function create(JwtPayloadUser $jwtPayloadUser): bool
    {
        return Auth::hasPrivilege(PrivilegeKey::ManageProject->value);
    }

    public function update(JwtPayloadUser $jwtPayloadUser, ProjectComment $projectComment): bool
    {
        return Auth::hasPrivilege(PrivilegeKey::ManageProject->value)
            && $projectComment->institution_user_id === Auth::user()?->institutionUserId;
    }

    public function delete(JwtPayloadUser $jwtPayloadUser, ProjectComment $projectComment): bool
    {
        return Auth::hasPrivilege(PrivilegeKey::ManageProject->value);
    }

    public static function scope()
    {
        return new Scope\ProjectCommentScope();
    }
}

// Scope resides in the same file with Policy to enforce scope creation with policy creation.

namespace App\Policies\Scope;

use App\Policies\ProjectPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope as IScope;

class ProjectCommentScope implements IScope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereHas('project', function (Builder $projectQuery) {
            $projectQuery->withGlobalScope('policy', ProjectPolicy::scope());
        });
    }
}
