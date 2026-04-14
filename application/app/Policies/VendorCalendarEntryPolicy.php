<?php

namespace App\Policies;

use App\Enums\PrivilegeKey;
use App\Models\Vendor;
use App\Models\VendorCalendarEntry;
use Illuminate\Support\Facades\Auth;
use KeycloakAuthGuard\Models\JwtPayloadUser;

class VendorCalendarEntryPolicy
{
    public function viewAny(JwtPayloadUser $user): bool
    {
        return Auth::hasPrivilege(PrivilegeKey::ReceiveProject->value) ||
            Auth::hasPrivilege(PrivilegeKey::ManageProject->value) ||
            Auth::hasPrivilege(PrivilegeKey::CreateProject->value) ||
            Vendor::withGlobalScope('policy', VendorPolicy::scope())
                ->where('institution_user_id', $user->institutionUserId)
                ->exists();
    }

    public function delete(JwtPayloadUser $user, VendorCalendarEntry $entry): bool
    {
        if (Auth::hasPrivilege(PrivilegeKey::ReceiveProject->value)) {
            return true;
        }

        return Vendor::where('institution_user_id', $user->institutionUserId)
            ->where('id', $entry->vendor_id)
            ->exists();
    }

    public function prebook(JwtPayloadUser $user): bool
    {
        return Auth::hasPrivilege(PrivilegeKey::ReceiveProject->value) ||
            Auth::hasPrivilege(PrivilegeKey::CreateProject->value);
    }

    public static function scope(): Scope\VendorCalendarEntryScope
    {
        return new Scope\VendorCalendarEntryScope();
    }
}

// Scope resides in the same file with Policy to enforce scope creation with policy creation.

namespace App\Policies\Scope;

use App\Policies\VendorPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope as IScope;

class VendorCalendarEntryScope implements IScope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereHas('vendor', function (Builder $vendorQuery): void {
            $vendorQuery->withGlobalScope('policy', VendorPolicy::scope());
        });
    }
}
