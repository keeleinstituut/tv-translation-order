<?php

namespace App\Policies;

use App\Enums\PrivilegeKey;
use App\Enums\TagType;
use App\Models\AuthUser;
use App\Models\Tag;
use App\Policies\Scope\TagScope;

class TagPolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return true;
    }

    public function create(AuthUser $user): bool
    {
        return $user->hasPrivilege(PrivilegeKey::AddTag);
    }

    public function update(AuthUser $user, Tag $tag): bool
    {
        return $user->hasPrivilege(PrivilegeKey::EditTag) &&
            $this->isFromSameInstitutionAsCurrentUser($user, $tag) &&
            !in_array($tag->type, [TagType::VendorSkill, TagType::TranslationDomain]);
    }

    public function delete(AuthUser $user, Tag $tag)
    {
        return $user->hasPrivilege(PrivilegeKey::DeleteTag) &&
            $this->isFromSameInstitutionAsCurrentUser($user, $tag) &&
            !in_array($tag->type, [TagType::VendorSkill, TagType::TranslationDomain]);
    }

    public function isFromSameInstitutionAsCurrentUser(AuthUser $user, Tag $tag): bool
    {
        return filled($user->institutionId)
            && (empty($tag->institution_id) || $user->institutionId === $tag->institution_id);
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

        $builder->where(fn (Builder $query) => $query
            ->where('institution_id', $currentUserInstitutionId)
            ->orWhereNull('institution_id')
        );
    }
}
