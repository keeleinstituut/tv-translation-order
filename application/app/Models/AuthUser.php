<?php

namespace App\Models;

use App\Enums\PrivilegeKey;
use KeycloakAuthGuard\Models\JwtPayloadUser;

class AuthUser extends JwtPayloadUser
{
    private bool|null $isVendor = null;

    private Vendor|null $vendor = null;

    public function isVendor(): bool
    {
        if (is_null($this->isVendor)) {
            $this->isVendor = Vendor::query()
                ->where('institution_user_id', $this->institutionUserId)
                ->exists();
        }

        return $this->isVendor;
    }

    public function vendor(): ?Vendor
    {
        if (!$this->isVendor()) {
            return null;
        }

        if (is_null($this->vendor)) {
            $this->vendor = Vendor::query()
                ->where('institution_user_id', $this->institutionUserId)
                ->first();
        }

        return $this->vendor;
    }

    public function isProjectManager(): bool
    {
        return $this->hasPrivilege(PrivilegeKey::ReceiveProject);
    }

    public function isClient(): bool
    {
        return $this->hasPrivilege(PrivilegeKey::CreateProject);
    }

    public function hasPrivilege(PrivilegeKey | string $privilege): bool
    {
        if (empty($this->privileges)) {
            return false;
        }

        $privilegeKey = is_string($privilege) ? PrivilegeKey::tryFrom($privilege) : $privilege;
        return in_array($privilegeKey, $this->privileges);
    }

    /**
     * @param  array<PrivilegeKey|string>  $privileges
     */
    public function hasAtLeastOnePrivilege(array $privileges): bool
    {
        return array_any($privileges, fn($privilege) => $this->hasPrivilege($privilege));

    }

    public function isClientOf(Project $project): bool
    {
        return filled($this->institutionUserId) &&
            $project->client_institution_user_id === $this->institutionUserId;
    }

    public function isAssignmentsCandidate(Project $project): bool
    {
        if (!$this->isVendor()) {
            return false;
        }

        if (empty($vendor = $this->vendor())) {
            return false;
        }

        return $project->candidates()->where('vendor_id', $vendor->id)->exists();
    }

    public function isInSameInstitutionAs(Project $project): bool
    {
        return filled($this->institutionId)
            && $this->institutionId === $project->institution_id;
    }

    public function isManagerOf(Project $project): bool
    {
        return filled($this->institutionUserId)
            && $project->manager_institution_user_id === $this->institutionUserId;
    }

    public function isAssignedTo(Assignment $assignment): bool
    {
        return filled($assignment->assigned_vendor_id)
            && $this->isVendor()
            && $assignment->assigned_vendor_id === $this->vendor()?->id;
    }

    public function isCandidateOf(Assignment $assignment)
    {
        return $this->isVendor() && filled($vendor = $this->vendor()) &&
            $assignment->candidates()->where('vendor_id', $vendor->id)->exists();
    }

    public function ownsVendor(Vendor $vendor): bool
    {
        return $vendor->institution_user_id === $this->institutionUserId;
    }

    public function isSharedProjectFromExternalInstitution(Project $project): bool
    {
        // Example of how to implement checks that will be needed for sharing projects with external translation agencies
        if ($project->institution_id !== $this->institutionId) {
            // check that it's a shared project via lookup of relation.
        }
        return false;
    }
}
