<?php

namespace App\Policies;

use App\Models\AuthUser;
use App\Models\VendorCalendarImport;

class VendorCalendarImportPolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return $user->isVendor();
    }

    public function create(AuthUser $user): bool
    {
        return $user->isVendor();
    }

    public function delete(AuthUser $user, VendorCalendarImport $import): bool
    {
        $vendor = $user->vendor();
        return $vendor && $vendor->id === $import->vendor_id;
    }

    public static function scope(): Scope\VendorCalendarImportScope
    {
        return new Scope\VendorCalendarImportScope();
    }
}

// Scope resides in the same file with Policy to enforce scope creation with policy creation.

namespace App\Policies\Scope;

use App\Policies\VendorPolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope as IScope;

class VendorCalendarImportScope implements IScope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->whereHas('vendor', function (Builder $vendorQuery): void {
            $vendorQuery->withGlobalScope('policy', VendorPolicy::scope());
        });
    }
}
