<?php

namespace App\Policies;

use App\Models\Vendor;
use App\Models\VendorCalendarImport;
use Illuminate\Support\Facades\Auth;
use KeycloakAuthGuard\Models\JwtPayloadUser;

class VendorCalendarImportPolicy
{
    public function create(JwtPayloadUser $jwtPayloadUser): bool
    {
        return Vendor::withGlobalScope('policy', VendorPolicy::scope())
            ->where('institution_user_id', $jwtPayloadUser->institutionUserId)
            ->exists();
    }

    public function delete(JwtPayloadUser $jwtPayloadUser, VendorCalendarImport $import): bool
    {
        return Vendor::withGlobalScope('policy', VendorPolicy::scope())
            ->where('institution_user_id', $jwtPayloadUser->institutionUserId)
            ->where('id', $import->vendor_id)
            ->exists();
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
