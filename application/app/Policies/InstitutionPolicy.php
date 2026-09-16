<?php

namespace App\Policies;

use App\Enums\PrivilegeKey;
use App\Models\AuthUser;

class InstitutionPolicy
{
    public function viewAny(AuthUser $user): bool
    {
        return $user->hasPrivilege(PrivilegeKey::ManageExternalPartner);
    }

    public function viewRequestOwners(AuthUser $user): bool
    {
        return $user->hasAtLeastOnePrivilege([
            PrivilegeKey::ManageExternalPartner,
            PrivilegeKey::RespondOutsourceRequest,
            PrivilegeKey::ViewOutsourceRequest,
        ]);
    }
}
