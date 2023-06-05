<?php

namespace App\Policies;

use App\Enums\PrivilegeKey;
use App\Models\Tag;
use App\Policies\Scope\TagScope;
use Illuminate\Support\Facades\Auth;
use KeycloakAuthGuard\Models\JwtPayloadUser;

class TagPolicy
{
    public function viewAny(JwtPayloadUser $jwtPayloadUser): bool
    {
        return Auth::hasPrivilege(PrivilegeKey::AddTag->value) || Auth::hasPrivilege(PrivilegeKey::EditTag->value);
    }

    public function create(JwtPayloadUser $jwtPayloadUser): bool
    {
        return Auth::hasPrivilege(PrivilegeKey::AddTag->value);
    }

    public function update(JwtPayloadUser $jwtPayloadUser, Tag $tag): bool
    {
        return Auth::hasPrivilege(PrivilegeKey::EditTag->value) && $this->isFromSameInstitutionAsCurrentUser($tag);
    }

    public function delete(JwtPayloadUser $jwtPayloadUser, Tag $tag)
    {
        return Auth::hasPrivilege(PrivilegeKey::DeleteTag->value) && $this->isFromSameInstitutionAsCurrentUser($tag);
    }

    public function isFromSameInstitutionAsCurrentUser(Tag $tag): bool
    {
        return filled($currentInstitutionId = Auth::user()?->institutionId)
            && $currentInstitutionId === $tag->institution_id;
    }

    // Should serve as a query enhancement to Eloquent queries
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
    public static function scope(): TagScope
    {
        return new Scope\TagScope();
    }
}

namespace App\Policies\Scope;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope as IScope;
use Illuminate\Support\Facades\Auth;

class TagScope implements IScope
{
    public function apply(Builder $builder, Model $model): void
    {
        if (empty($currentUserInstitutionId = Auth::user()?->institutionId)) {
            abort(401);
        }

        $builder->where('institution_id', $currentUserInstitutionId);
    }
}
