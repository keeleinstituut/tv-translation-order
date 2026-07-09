<?php

namespace App\Policies;

use App\Enums\PrivilegeKey;
use App\Models\AuthUser;
use App\Models\VendorEmergencySchedule;

class VendorEmergencySchedulePolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return $user->hasPrivilege(PrivilegeKey::EditVendorDatabase);
    }

    public function create(AuthUser $user): bool
    {
        return $user->hasPrivilege(PrivilegeKey::EditVendorDatabase);
    }

    public function delete(AuthUser $user, VendorEmergencySchedule $emergencySchedule): bool
    {
        return $user->hasPrivilege(PrivilegeKey::EditVendorDatabase);
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
