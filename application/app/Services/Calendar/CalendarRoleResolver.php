<?php

namespace App\Services\Calendar;

use App\Enums\CalendarRole;
use App\Enums\PrivilegeKey;
use App\Models\Vendor;
use App\Policies\VendorPolicy;
use Illuminate\Support\Facades\Auth;

class CalendarRoleResolver
{
    private ?CalendarRole $role = null;

    private ?Vendor $vendor = null;

    private bool $resolved = false;

    public function resolve(): CalendarRole
    {
        if (!$this->resolved) {
            $this->doResolve();
        }

        return $this->role;
    }

    public function getVendor(): ?Vendor
    {
        if (!$this->resolved) {
            $this->doResolve();
        }

        return $this->vendor;
    }

    public function getInstitutionId(): string
    {
        return Auth::user()->institutionId;
    }

    public function getInstitutionUserId(): string
    {
        return Auth::user()->institutionUserId;
    }

    private function doResolve(): void
    {
        $this->resolved = true;

        if (Auth::hasPrivilege(PrivilegeKey::ManageProject->value)) {
            $this->role = CalendarRole::ProjectManager;
            return;
        }

        if (Auth::hasPrivilege(PrivilegeKey::CreateProject->value)) {
            $this->role = CalendarRole::Client;
            return;
        }

        $this->vendor = Vendor::withGlobalScope('policy', VendorPolicy::scope())
            ->where('institution_user_id', $this->getInstitutionUserId())
            ->first();

        if ($this->vendor) {
            $this->role = CalendarRole::Vendor;
            return;
        }

        $this->role = CalendarRole::Unknown;
    }
}
