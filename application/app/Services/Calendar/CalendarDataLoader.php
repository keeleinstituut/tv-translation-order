<?php

namespace App\Services\Calendar;

use App\Repositories\Calendar\CalendarVendorRepository;
use App\Repositories\Calendar\VendorLanguageCoverageRepository;
use App\Repositories\Calendar\WorktimeRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

readonly class CalendarDataLoader
{
    public function __construct(
        private VendorLanguageCoverageRepository $coverageRepo,
        private CalendarVendorRepository         $vendorRepo,
        private WorktimeRepository               $worktimeRepo,
    )
    {
    }

    /**
     * Load full calendar data: coverage, worktimes, and emergency schedules.
     *
     * Used by Day, Week, Search, and VendorCalendar controllers.
     */
    public function loadFull(string $institutionId, Carbon $start, Carbon $end): CalendarData
    {
        $resolved = $this->resolveFilteredCoverage($institutionId, $start, $end);

        if ($resolved === null) {
            return $this->emptyCalendarData($institutionId);
        }

        return new CalendarData(
            institutionId: $institutionId,
            internalVendorIds: $resolved['internalVendorIds'],
            importedCalendarVendorIds: $resolved['importedCalendarVendorIds'],
            institutionWorktime: $this->worktimeRepo->getInstitutionWorktime($institutionId),
            institutionUserWorktimes: $this->worktimeRepo->getUserWorktimes($resolved['institutionUserIds']),
            emergencySchedules: $this->vendorRepo->getEmergencySchedulesForVendors($resolved['internalVendorIds'], $start, $end),
            vendorLanguageCoverages: $resolved['vendorLanguageCoverages'],
        );
    }

    /**
     * Load only coverage + import data (2 queries).
     *
     * Used by MonthController TPM view which only needs coverage data up front.
     */
    public function loadCoverageOnly(string $institutionId, Carbon $start, Carbon $end): CalendarData
    {
        $resolved = $this->resolveFilteredCoverage($institutionId, $start, $end);

        if ($resolved === null) {
            return $this->emptyCalendarData($institutionId);
        }

        return new CalendarData(
            institutionId: $institutionId,
            internalVendorIds: $resolved['internalVendorIds'],
            importedCalendarVendorIds: $resolved['importedCalendarVendorIds'],
            institutionWorktime: null,
            institutionUserWorktimes: null,
            emergencySchedules: $this->vendorRepo->getEmergencySchedulesForVendors($resolved['internalVendorIds'], $start, $end),
            vendorLanguageCoverages: $resolved['vendorLanguageCoverages'],
        );
    }

    /**
     * Resolve and filter vendor language coverages for the given institution and period.
     *
     * @return array{vendorLanguageCoverages: Collection, internalVendorIds: Collection, importedCalendarVendorIds: Collection, institutionUserIds: Collection}|null
     */
    private function resolveFilteredCoverage(string $institutionId, Carbon $start, Carbon $end): ?array
    {
        $vendorLanguageCoverages = $this->coverageRepo
            ->getCoverageForInstitutionMainLanguages($institutionId);

        if ($vendorLanguageCoverages->isEmpty()) {
            return null;
        }

        $internalVendorIds = $vendorLanguageCoverages->pluck('vendor_id')->unique()->values();

        $vendorIdsWithImport = $this->vendorRepo->getVendorIdsWithImportInPeriod($internalVendorIds, $start, $end);

        $vendorLanguageCoverages = $vendorLanguageCoverages->filter(
            fn($row) => $vendorIdsWithImport->contains($row->vendor_id)
        );

        if ($vendorLanguageCoverages->isEmpty()) {
            return null;
        }

        return [
            'vendorLanguageCoverages' => $vendorLanguageCoverages,
            'internalVendorIds' => $internalVendorIds,
            'importedCalendarVendorIds' => $vendorLanguageCoverages->pluck('vendor_id')->unique()->values(),
            'institutionUserIds' => $vendorLanguageCoverages->pluck('institution_user_id')->unique(),
        ];
    }

    private function emptyCalendarData(string $institutionId): CalendarData
    {
        return new CalendarData(
            institutionId: $institutionId,
            internalVendorIds: collect(),
            importedCalendarVendorIds: collect(),
            institutionWorktime: null,
            institutionUserWorktimes: null,
            emergencySchedules: null,
            vendorLanguageCoverages: collect(),
        );
    }
}
