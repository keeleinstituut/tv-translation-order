<?php

namespace App\Models;

use App\Enums\PrivilegeKey;
use App\Enums\OutsourceOfferStatus;
use App\Models\CachedEntities\Institution;
use KeycloakAuthGuard\Models\JwtPayloadUser;

/**
 * @property string|null $id
 * @property string|null $institutionId
 * @property string|null $institutionUserId
 * @property array<int, string> $privileges
 */
class AuthUser extends JwtPayloadUser
{
    private bool|null $isVendor = null;

    private Vendor|null $vendor = null;
    private Institution|null $institution = null;

    public function institution(): Institution|null
    {
        if (is_null($this->institution)) {
            $this->institution = Institution::query()
                ->find($this->institutionId);
        }

        return $this->institution;
    }

    public function belongsToTranslationAgency(): bool
    {
        return $this->institution()?->isTranslationAgency() === true;
    }

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

    public function hasPrivilege(PrivilegeKey | string $privilege): bool
    {
        if (empty($this->privileges)) {
            return false;
        }

        $privilegeKey = is_string($privilege) ? PrivilegeKey::tryFrom($privilege) : $privilege;
        return in_array($privilegeKey->value, $this->privileges);
    }

    /**
     * @param  array<PrivilegeKey|string>  $privileges
     */
    public function hasAtLeastOnePrivilege(array $privileges): bool
    {
        return array_any($privileges, fn($privilege) => $this->hasPrivilege($privilege));

    }

    public function isClientOfProject(Project $project): bool
    {
        return filled($this->institutionUserId) &&
            $project->client_institution_user_id === $this->institutionUserId;
    }

    public function hasAssignmentCandidateAccessToProject(Project $project): bool
    {
        if (!$this->isVendor()) {
            return false;
        }

        if (empty($vendor = $this->vendor())) {
            return false;
        }

        return $project->candidates()->where('vendor_id', $vendor->id)->exists();
    }

    public function isInSameInstitutionAsProject(Project $project): bool
    {
        return filled($this->institutionId)
            && $this->institutionId === $project->institution_id;
    }

    public function isInSameInstitutionAsSubProject(SubProject $subProject): bool
    {
        if (empty($this->institutionId)) {
            return false;
        }

        return Project::query()
            ->where('id', $subProject->project_id)
            ->where('institution_id', $this->institutionId)
            ->exists();
    }

    public function isManagerOfProject(Project $project): bool
    {
        return filled($this->institutionUserId)
            && $project->manager_institution_user_id === $this->institutionUserId;
    }

    public function hasSharedPartnerAccessToAssignment(Assignment $assignment): bool
    {
        if (empty($this->institutionId)) {
            return false;
        }

        return OutsourceOffer::query()
            ->where('institution_id', $this->institutionId)
            ->whereHas('outsourceRequest', fn ($q) => $q->where('assignment_id', $assignment->id))
            ->exists();
    }

    public function hasActivePartnerAccessToAssignment(Assignment $assignment): bool
    {
        if (empty($this->institutionId)) {
            return false;
        }

        return OutsourceOffer::query()
            ->where('institution_id', $this->institutionId)
            ->where('status', OutsourceOfferStatus::OfferAccepted)
            ->whereHas('outsourceRequest', fn ($q) => $q->where('assignment_id', $assignment->id))
            ->exists();
    }

    public function hasSharedPartnerAccessToSubProject(SubProject $subProject, bool $requireSourceFiles = false): bool
    {
        if (empty($this->institutionId)) {
            return false;
        }

        return OutsourceOffer::query()
            ->where('institution_id', $this->institutionId)
            ->whereHas('outsourceRequest.assignment.subProject',
                fn ($q) => $q->where('id', $subProject->id))
            ->whereHas('outsourceRequest', function ($q) use ($requireSourceFiles) {
                if ($requireSourceFiles) {
                    $q->where('include_source_files', true);
                }
            })
            ->exists();
    }

    public function hasActivePartnerAccessToSubProject(SubProject $subProject): bool
    {
        if (empty($this->institutionId)) {
            return false;
        }

        return OutsourceOffer::query()
            ->where('institution_id', $this->institutionId)
            ->where('status', OutsourceOfferStatus::OfferAccepted)
            ->whereHas('outsourceRequest.assignment.subProject', fn ($q) => $q->where('id', $subProject->id))
            ->exists();
    }

    public function hasSharedPartnerAccessToProject(Project $project, bool $requireSourceFiles = false): bool
    {
        if (empty($this->institutionId)) {
            return false;
        }

        return OutsourceOffer::query()
            ->where('institution_id', $this->institutionId)
            ->whereHas('outsourceRequest.assignment.subProject',
                fn ($q) => $q->where('project_id', $project->id))
            ->whereHas('outsourceRequest', function ($q) use ($requireSourceFiles) {
                if ($requireSourceFiles) {
                    $q->where('include_source_files', true);
                }
            })->exists();
    }

    public function hasActivePartnerAccessToProject(Project $project): bool
    {
        if (empty($this->institutionId)) {
            return false;
        }

        return OutsourceOffer::query()
            ->where('institution_id', $this->institutionId)
            ->where('status', OutsourceOfferStatus::OfferAccepted)
            ->whereHas('outsourceRequest.assignment.subProject', fn ($q) => $q->where('project_id', $project->id))
            ->exists();
    }
}
