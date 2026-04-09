<?php

namespace App\Policies;

use App\Enums\PrivilegeKey;
use Illuminate\Support\Facades\Auth;
use KeycloakAuthGuard\Models\JwtPayloadUser;

class CalendarSettingPolicy
{
    public function viewAny(JwtPayloadUser $jwtPayloadUser): bool
    {
        return Auth::hasPrivilege(PrivilegeKey::EditInstitution->value);
    }

    public function update(JwtPayloadUser $jwtPayloadUser): bool
    {
        return Auth::hasPrivilege(PrivilegeKey::EditInstitution->value);
    }

    public static function scope(): Scope\CalendarSettingScope
    {
        return new Scope\CalendarSettingScope();
    }
}

// Scope resides in the same file with Policy to enforce scope creation with policy creation.

namespace App\Policies\Scope;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope as IScope;
use Illuminate\Support\Facades\Auth;

class CalendarSettingScope implements IScope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('institution_id', Auth::user()->institutionId);
    }
}
