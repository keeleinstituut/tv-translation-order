<?php

namespace App\Services\Calendar;

use App\Models\VendorCalendarEntry;
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
     * Load calendar data with entries, worktimes, and emergency schedules (6 queries).
     *
     * Used by Day, Week, Search, and VendorCalendar controllers.
     */
    public function loadWithEntries(string $institutionId, Carbon $start, Carbon $end): CalendarData
    {
        $resolved = $this->resolveFilteredCoverage($institutionId, $start, $end);

        if ($resolved === null) {
            return $this->emptyCalendarData($institutionId);
        }

        return new CalendarData(
            institutionId: $institutionId,
            coverageByLanguage: $resolved['vendorLanguageCoverages']->groupBy('language_id'),
            allVendorIds: $resolved['allVendorIds'],
            importedCalendarVendorIds: $resolved['importedCalendarVendorIds'],
            institutionWorktime: $this->worktimeRepo->getInstitutionWorktime($institutionId),
            institutionUserWorktimes: $this->worktimeRepo->getUserWorktimes($resolved['institutionUserIds']),
            entriesByVendor: $this->getEntriesWithOverlappingTimestamps($resolved['importedCalendarVendorIds'], $start, $end),
            emergencySchedules: $this->vendorRepo->getEmergencySchedulesForVendors($resolved['allVendorIds'], $start, $end),
            vendorLanguageCoverages: $resolved['vendorLanguageCoverages'],
        );
    }

    /**
     * Load only coverage + import data without entries (2 queries).
     *
     * Used by MonthController TPM view which only needs coverage data up front.
     */
    public function loadWithoutEntries(string $institutionId, Carbon $start, Carbon $end): CalendarData
    {
        $resolved = $this->resolveFilteredCoverage($institutionId, $start, $end);

        if ($resolved === null) {
            return $this->emptyCalendarData($institutionId);
        }

        return new CalendarData(
            institutionId: $institutionId,
            coverageByLanguage: $resolved['vendorLanguageCoverages']->groupBy('language_id'),
            allVendorIds: $resolved['allVendorIds'],
            importedCalendarVendorIds: $resolved['importedCalendarVendorIds'],
            institutionWorktime: null,
            institutionUserWorktimes: null,
            entriesByVendor: null,
            emergencySchedules: null,
            vendorLanguageCoverages: $resolved['vendorLanguageCoverages'],
        );
    }

    /**
     * Resolve and filter vendor language coverages for the given institution and period.
     *
     * @return array{vendorLanguageCoverages: Collection, allVendorIds: Collection, importedCalendarVendorIds: Collection, institutionUserIds: Collection}|null
     */
    private function resolveFilteredCoverage(string $institutionId, Carbon $start, Carbon $end): ?array
    {
        $vendorLanguageCoverages = $this->coverageRepo
            ->getCoverageForInstitutionMainLanguages($institutionId);

        if ($vendorLanguageCoverages->isEmpty()) {
            return null;
        }

        $allVendorIds = $vendorLanguageCoverages->pluck('vendor_id')->unique()->values();

        $vendorIdsWithImport = $this->vendorRepo->getVendorIdsWithImportInPeriod($allVendorIds, $start, $end);

        $vendorLanguageCoverages = $vendorLanguageCoverages->filter(
            fn($row) => $vendorIdsWithImport->contains($row->vendor_id)
        );

        if ($vendorLanguageCoverages->isEmpty()) {
            return null;
        }

        return [
            'vendorLanguageCoverages' => $vendorLanguageCoverages,
            'allVendorIds' => $allVendorIds,
            'importedCalendarVendorIds' => $vendorLanguageCoverages->pluck('vendor_id')->unique()->values(),
            'institutionUserIds' => $vendorLanguageCoverages->pluck('institution_user_id')->unique(),
        ];
    }

    /**
     * Get overlapping entries as timestamp arrays, grouped by vendor_id.
     *
     * @param  Collection<int, string>  $vendorIds
     * @return Collection<string, Collection<int, array{id: string, vendor_id: string, start_ts: int, end_ts: int}>>
     */
    private function getEntriesWithOverlappingTimestamps(Collection $vendorIds, Carbon $start, Carbon $end): Collection
    {
        if ($vendorIds->isEmpty()) {
            return collect();
        }

        return VendorCalendarEntry::whereIn('vendor_id', $vendorIds)
            ->overlapping($start, $end)
            ->get(['id', 'vendor_id', 'start_at', 'end_at'])
            ->map(fn(VendorCalendarEntry $entry) => [
                'id' => $entry->id,
                'vendor_id' => $entry->vendor_id,
                'start_ts' => Carbon::parse($entry->start_at)->utc()->timestamp,
                'end_ts' => Carbon::parse($entry->end_at)->utc()->timestamp,
            ])
            ->groupBy('vendor_id');
    }

    private function emptyCalendarData(string $institutionId): CalendarData
    {
        return new CalendarData(
            institutionId: $institutionId,
            coverageByLanguage: collect(),
            allVendorIds: collect(),
            importedCalendarVendorIds: collect(),
            institutionWorktime: null,
            institutionUserWorktimes: null,
            entriesByVendor: null,
            emergencySchedules: null,
            vendorLanguageCoverages: collect(),
        );
    }
}
