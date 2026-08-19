<?php

namespace App\Policies;

use App\Enums\PrivilegeKey;
use App\Models\AuthUser;
use App\Models\VendorCalendarEntry;

class VendorCalendarEntryPolicy
{
    public function viewAny(AuthUser $user): bool
    {
        if ($user->hasAtLeastOnePrivilege([PrivilegeKey::ReceiveProject, PrivilegeKey::ManageProject])) {
            return true;
        }

        if ($user->hasPrivilege(PrivilegeKey::CreateProject) && ! $user->belongsToTranslationAgency()) {
            return true;
        }

        return $user->isVendor();
    }

    public function create(AuthUser $user): bool
    {
        return $user->hasAtLeastOnePrivilege([PrivilegeKey::ManageProject, PrivilegeKey::ReceiveProject]);
    }

    public function delete(AuthUser $user, VendorCalendarEntry $entry): bool
    {
        if ($user->hasPrivilege(PrivilegeKey::ReceiveProject)) {
            return true;
        }

        $vendor = $user->vendor();
        return $vendor && $vendor->id === $entry->vendor_id;
    }

    public function prebook(AuthUser $user): bool
    {
        if ($user->hasPrivilege(PrivilegeKey::ReceiveProject)) {
            return true;
        }

        return $user->hasPrivilege(PrivilegeKey::CreateProject) && ! $user->belongsToTranslationAgency();
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
