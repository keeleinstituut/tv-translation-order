<?php

namespace App\Policies;

use App\Enums\PrivilegeKey;
use App\Models\VendorEmergencySchedule;
use Illuminate\Support\Facades\Auth;
use KeycloakAuthGuard\Models\JwtPayloadUser;

class VendorEmergencySchedulePolicy
{
    public function viewAny(JwtPayloadUser $jwtPayloadUser): bool
    {
        return Auth::hasPrivilege(PrivilegeKey::EditVendorDatabase->value);
    }

    public function create(JwtPayloadUser $jwtPayloadUser): bool
    {
        return Auth::hasPrivilege(PrivilegeKey::EditVendorDatabase->value);
    }

    public function delete(JwtPayloadUser $jwtPayloadUser, VendorEmergencySchedule $emergencySchedule): bool
    {
        return Auth::hasPrivilege(PrivilegeKey::EditVendorDatabase->value);
    }

    public static function scope()
    {
        return new Scope\VendorEmergencyScheduleScope();
    }
}

// Scope resides in the same file with Policy to enforce scope creation with policy creation.

namespace App\Policies\Scope;

use App\Policies\VendorPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope as IScope;

class VendorEmergencyScheduleScope implements IScope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereHas('vendor', function (Builder $vendorQuery) {
            $vendorQuery->withGlobalScope('policy', VendorPolicy::scope());
        });
    }
}
