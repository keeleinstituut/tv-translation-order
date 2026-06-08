<?php

namespace App\Policies;

use App\Enums\PrivilegeKey;
use App\Models\AuthUser;
use App\Models\OutsourceOffer;

class OutsourceOfferPolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return $user->hasPrivilege(PrivilegeKey::RespondOutsourceRequest);
    }

    public function view(AuthUser $user, OutsourceOffer $offer): bool
    {
        return $user->hasAtLeastOnePrivilege([
            PrivilegeKey::ViewOutsourceRequest,
            PrivilegeKey::RespondOutsourceRequest,
        ]);
    }

    public function accept(AuthUser $user, OutsourceOffer $offer): bool
    {
        return $user->hasPrivilege(PrivilegeKey::RespondOutsourceRequest);
    }

    public function decline(AuthUser $user, OutsourceOffer $offer): bool
    {
        return $user->hasPrivilege(PrivilegeKey::RespondOutsourceRequest);
    }

    public static function scope(): Scope\OutsourceOfferScope
    {
        return new Scope\OutsourceOfferScope();
    }
}

// Scope resides in the same file with Policy to enforce scope creation with policy creation.

namespace App\Policies\Scope;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope as IScope;
use Illuminate\Support\Facades\Auth;

class OutsourceOfferScope implements IScope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('institution_id', Auth::user()->institutionId);
    }
}
