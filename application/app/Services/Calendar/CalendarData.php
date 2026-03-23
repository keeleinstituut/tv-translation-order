<?php

namespace App\Services\Calendar;

use App\Models\VendorEmergencySchedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Readonly data object carrying all calendar data for a given institution and period.
 *
 * Replaces CalendarDayContext and CalendarAggregationContext with a single class.
 * Has typed accessor methods for common vendor lookups.
 */
readonly class CalendarData
{
    /** @var Collection<string, Collection<int, array>> language_id => coverage row arrays */
    public Collection $coverageByLanguage;

    /** @var Collection<string, Collection<int, string>> vendor_id => language IDs */
    private Collection $vendorLanguages;

    /** @var Collection<string, string> vendor_id => institution_user_id */
    private Collection $vendor2institutionUserId;

    /**
     * @param  string  $institutionId
     * @param  Collection<int, string>  $internalVendorIds
     * @param  Collection<int, string>  $importedCalendarVendorIds  vendors with calendar imports for the period
     * @param  array<string, mixed>|null  $institutionWorktime
     * @param  Collection<string, array<string, mixed>>|null  $institutionUserWorktimes  keyed by institution_user_id
     * @param  Collection<string, Collection<int, VendorEmergencySchedule>>|null  $emergencySchedules  grouped by vendor_id
     * @param  Collection<int, array>  $vendorLanguageCoverages  flat coverage rows (all shapes derived from this)
     */
    public function __construct(
        public string      $institutionId,
        public Collection  $internalVendorIds,
        public Collection  $importedCalendarVendorIds,
        public ?array      $institutionWorktime,
        public ?Collection $institutionUserWorktimes,
        public ?Collection $emergencySchedules,
        Collection         $vendorLanguageCoverages,
    ) {
        $this->coverageByLanguage = $vendorLanguageCoverages->groupBy('language_id');

        $this->vendor2institutionUserId = $vendorLanguageCoverages
            ->pluck('institution_user_id', 'vendor_id');

        $this->vendorLanguages = $vendorLanguageCoverages
            ->groupBy('vendor_id')
            ->map(fn(Collection $rows) => $rows->pluck('language_id')->unique()->values());
    }

    public function resolveInstitutionUserId(string $vendorId): ?string
    {
        return $this->vendor2institutionUserId[$vendorId] ?? null;
    }

    /**
     * Resolve the effective worktime for a vendor (chains vendor -> IU -> worktime).
     *
     * @return array<string, mixed>|null
     */
    public function getVendorWorktime(string $vendorId): ?array
    {
        $institutionUserId = $this->resolveInstitutionUserId($vendorId);

        if ($institutionUserId === null) {
            return null;
        }

        /** @var array|null $institutionUserWorktime */
        $institutionUserWorktime = $this->institutionUserWorktimes?->get($institutionUserId);

        return $institutionUserWorktime;
    }

    /**
     * @return Collection<int, string>
     */
    public function getLanguagesForVendor(string $vendorId): Collection
    {
        return $this->vendorLanguages->get($vendorId, collect());
    }

    /**
     * @return Collection<int, VendorEmergencySchedule>
     */
    public function getEmergencySchedulesForVendor(string $vendorId): Collection
    {
        return $this->emergencySchedules?->get($vendorId, collect()) ?? collect();
    }

    /**
     * @return Collection<int, string>
     */
    public function vendorIdsWithEmergencySchedule(): Collection
    {
        if ($this->emergencySchedules === null) {
            return collect();
        }

        return $this->emergencySchedules->keys();
    }

    public function hasActiveEmergencySchedule(string $vendorId, Carbon $date): bool
    {
        return $this->getEmergencySchedulesForVendor($vendorId)->contains(
            fn(VendorEmergencySchedule $s) => $s->start_date->lte($date) && $s->end_date->gte($date)
        );
    }
}
