<?php

namespace App\Services\Calendar;

use App\Models\Project;
use App\Models\Vendor;
use App\Models\VendorCalendarEntry;
use App\Repositories\Calendar\CalendarVendorRepository;
use App\Repositories\Calendar\WorktimeRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

readonly class SlotMatchingService
{
    public function __construct(
        private CalendarSettingsResolver   $calendarSettings,
        private VendorWorkingHoursResolver $workingHoursResolver,
        private CalendarVendorRepository   $vendorRepo,
        private WorktimeRepository         $worktimeRepo,
    )
    {
    }

    /**
     * Check whether a specific vendor has no conflicting calendar entries in the slot.
     */
    public function isVendorAvailableForSlot(
        string  $vendorId,
        Carbon  $startAt,
        Carbon  $endAt,
        ?string $excludePrebookUserId = null,
        ?string $excludeAssignmentId = null,
    ): bool
    {
        return !VendorCalendarEntry::where('vendor_id', $vendorId)
            ->overlapping($startAt, $endAt)
            ->when($excludePrebookUserId, fn($q) => $q
                ->where(fn($inner) => $inner
                    ->whereNull('prebook_institution_user_id')
                    ->orWhere('prebook_institution_user_id', '!=', $excludePrebookUserId)
                )
            )
            ->when($excludeAssignmentId, fn($q) => $q
                ->where(fn($inner) => $inner
                    ->whereNull('assignment_id')
                    ->orWhere('assignment_id', '!=', $excludeAssignmentId)
                )
            )
            ->exists();
    }

    /**
     * Algorithm 2: Pick the single best internal vendor from the available set.
     *
     * Priority:
     *   1. If one internal vendor returns it
     *   2. Tag overlap with a project -> if exactly one match, return it; if multiple, narrow
     *   3. Least weekly assignment workload
     *   4. Least daily assignment workload
     *   5. Alphabetical (surname, then forename)
     */
    public function pickBestInternalVendor(
        string      $languageId,
        Carbon      $eventStartAt,
        Carbon      $eventEndAt,
        string      $institutionId,
        Collection  $tagIds,
        ?string     $excludePrebookUserId = null,
        ?Collection $excludeVendorIds = null,
    ): ?Vendor
    {
        $internals = $this->findAvailableVendors(
            $languageId,
            $eventStartAt,
            $eventEndAt,
            $institutionId,
            $excludePrebookUserId,
        )->filter(fn(Vendor $v) => $v->is_internal);

        if ($excludeVendorIds?->isNotEmpty()) {
            $internals = $internals->reject(fn(Vendor $v) => $excludeVendorIds->contains($v->id));
        }

        if ($internals->isEmpty()) {
            return null;
        }

        if ($internals->count() === 1) {
            return $internals->first();
        }

        $internals = $this->narrowByTagMatch($internals, $tagIds);
        if ($internals->count() === 1) {
            return $internals->first();
        }

        $internals = $this->narrowByWorkload($internals, $eventStartAt, 'week');
        if ($internals->count() === 1) {
            return $internals->first();
        }

        $internals = $this->narrowByWorkload($internals, $eventEndAt, 'day');
        if ($internals->count() === 1) {
            return $internals->first();
        }

        return $this->pickAlphabetically($internals);
    }

    public function pickBestInternalVendorForProject(
        Project     $project,
        ?string     $excludePrebookUserId = null,
        ?Collection $excludeVendorIds = null,
    ): ?Vendor
    {
        return $this->pickBestInternalVendor(
            $project->subProjects->first()->destination_language_classifier_value_id,
            $project->event_start_at,
            $project->event_end_at,
            $project->institution_id,
            $project->tags->pluck('id'),
            $excludePrebookUserId,
            $excludeVendorIds,
        );
    }

    /**
     * Algorithm 3: Rank external vendors by the cheapest hourly rate for the cascade.
     *
     * @return Collection<int, Vendor>
     */
    public function rankExternalVendorCascade(
        string $sourceLanguageId,
        string $destinationLanguageId,
        Carbon $eventStartAt,
        Carbon $eventEndAt,
        string $institutionId
    ): Collection
    {
        /** @var \Illuminate\Database\Eloquent\Collection $externals */
        $externals = $this->findAvailableVendors(
            $destinationLanguageId,
            $eventStartAt,
            $eventEndAt,
            $institutionId,
        )->filter(fn(Vendor $v) => !$v->is_internal);

        $skillId = $this->calendarSettings->getDefaultCalendarSkillId($institutionId);

        if ($externals->isEmpty()) {
            return $externals;
        }

        $externals->load(['prices' => fn($q) => $q
            ->where('src_lang_classifier_value_id', $sourceLanguageId)
            ->where('dst_lang_classifier_value_id', $destinationLanguageId)
            ->where('skill_id', $skillId),
        ]);

        return $externals
            ->sortBy(fn(Vendor $vendor) => $vendor->prices->first()?->hour_fee ?? PHP_FLOAT_MAX)
            ->values();
    }

    /**
     * @param Project $project
     * @return Collection<int, Vendor>
     */
    public function rankExternalVendorCascadeForProject(Project $project): Collection
    {
        $subProject = $project->subProjects->first();

        return $this->rankExternalVendorCascade(
            $subProject->source_language_classifier_value_id,
            $subProject->destination_language_classifier_value_id,
            $project->event_start_at,
            $project->event_end_at,
            $project->institution_id,
        );
    }

    /**
     * Find all vendors available for a given time slot and language.
     *
     * Internal vendors: language coverage + calendar import + no conflicts + within working window.
     * External vendors: language coverage + no conflicts.
     *
     * @return Collection<int, Vendor>
     */
    public function findAvailableVendorsForSlot(
        string $languageId,
        Carbon $startAt,
        Carbon $endAt,
        string $institutionId,
    ): Collection
    {
        $vendors = Vendor::query()
            ->servingLanguage($languageId, $institutionId)
            ->availableForSlot($startAt, $endAt)
            ->with('institutionUser')
            ->get();

        if ($vendors->isEmpty()) {
            return $vendors;
        }

        [$internals, $externals] = $vendors->partition(fn(Vendor $v) => $v->is_internal);

        if ($internals->isNotEmpty()) {
            $internals = $this->filterInternalsByCalendarImport($internals, $startAt);
            $internals = $this->filterInternalsByWorkingHours($internals, $startAt, $endAt, $institutionId);
        }

        return $internals->merge($externals)->values();
    }

    /**
     * Keep only internal vendors that have imported their calendar covering the slot date.
     *
     * @param Collection<int, Vendor> $internals
     * @return Collection<int, Vendor>
     */
    private function filterInternalsByCalendarImport(Collection $internals, Carbon $startAt): Collection
    {
        $dayStart = $startAt->copy()->startOfDay();
        $dayEnd = $startAt->copy()->endOfDay();

        $vendorIdsWithImport = $this->vendorRepo->getVendorIdsWithImportInPeriod(
            $internals->pluck('id'), $dayStart, $dayEnd
        );

        return $internals->filter(fn(Vendor $v) => $vendorIdsWithImport->contains($v->id));
    }

    /**
     * Keep only internal vendors whose working window fully covers the requested slot.
     *
     * @param Collection<int, Vendor> $internals
     * @return Collection<int, Vendor>
     */
    private function filterInternalsByWorkingHours(
        Collection $internals,
        Carbon     $startAt,
        Carbon     $endAt,
        string     $institutionId,
    ): Collection
    {
        if ($internals->isEmpty()) {
            return $internals;
        }

        $institutionWorktime = $this->worktimeRepo->getInstitutionWorktime($institutionId);

        $iuWorktimes = $this->worktimeRepo->getUserWorktimes(
            $internals->pluck('institution_user_id')
        );

        return $internals->filter(function (Vendor $v) use ($iuWorktimes, $institutionWorktime, $startAt, $endAt) {
            $window = $this->workingHoursResolver->workingWindowInSlot(
                $iuWorktimes->get($v->institution_user_id),
                $institutionWorktime,
                $startAt,
                $endAt,
            );

            return $window !== null
                && $window[0] <= $startAt->timestamp
                && $window[1] >= $endAt->timestamp;
        });
    }

    /**
     * Algorithm 1: Find all vendors available for a given time slot and language.
     *
     * @return Collection<int, Vendor>
     */
    private function findAvailableVendors(
        string  $languageId,
        Carbon  $eventStartAt,
        Carbon  $eventEndAt,
        string  $institutionId,
        ?string $excludePrebookUserId = null,
    ): Collection
    {
        return Vendor::whereRelation('institutionUser', 'institution->id', $institutionId)
            ->servingLanguage($languageId, $institutionId)
            ->withCalendarImportFor($eventStartAt->copy()->startOfDay())
            ->availableForSlot($eventStartAt, $eventEndAt, $excludePrebookUserId)
            ->withoutActiveEmergencySchedule($eventStartAt->copy()->startOfDay())
            ->with('institutionUser')
            ->get();
    }

    /**
     * @param Collection<int, Vendor> $vendors
     * @return Collection<int, Vendor>
     */
    private function narrowByTagMatch(Collection $vendors, Collection $tagIds): Collection
    {
        if ($tagIds->isEmpty()) {
            return $vendors;
        }

        $vendors->load('tags');

        $matched = $vendors->filter(
            fn(Vendor $v) => $v->tags->pluck('id')
                ->intersect($tagIds)
                ->isNotEmpty()
        );

        return $matched->isEmpty() ? $vendors : $matched;
    }

    /**
     * Keep only vendors with the minimum assignment workload in the period.
     *
     * @param Collection<int, Vendor> $vendors
     * @param 'week'|'day' $period
     * @return Collection<int, Vendor>
     */
    private function narrowByWorkload(Collection $vendors, Carbon $eventStartAt, string $period): Collection
    {
        if ($period === 'week') {
            $periodStart = $eventStartAt->copy()->startOfWeek()->utc();
            $periodEnd = $eventStartAt->copy()->endOfWeek()->utc();
        } else {
            $periodStart = $eventStartAt->copy()->startOfDay()->utc();
            $periodEnd = $eventStartAt->copy()->endOfDay()->utc();
        }

        $vendorIds = $vendors->pluck('id');
        $workloads = $vendorIds->isEmpty() ? collect() : VendorCalendarEntry::query()
            ->selectRaw(
                'vendor_id, SUM(EXTRACT(EPOCH FROM LEAST(end_at, ?)) - EXTRACT(EPOCH FROM GREATEST(start_at, ?)))::integer AS total_seconds',
                [$periodEnd, $periodStart]
            )
            ->whereIn('vendor_id', $vendorIds)
            ->assignmentsOnly()
            ->overlapping($periodStart, $periodEnd)
            ->groupBy('vendor_id')
            ->pluck('total_seconds', 'vendor_id')
            ->map(fn($val) => (int) $val);

        $minWorkload = $vendors->min(fn(Vendor $v) => $workloads->get($v->id, 0));

        return $vendors
            ->filter(fn(Vendor $v) => $workloads->get($v->id, 0) === $minWorkload)
            ->values();
    }

    /**
     * Final tiebreaker: pick alphabetically by surname then forename.
     */
    private function pickAlphabetically(Collection $vendors): Vendor
    {
        /** @var Vendor */
        return $vendors
            ->sort(function (Vendor $a, Vendor $b): int {
                return strcasecmp(
                    data_get($a->institutionUser, 'user.surname', ''),
                    data_get($b->institutionUser, 'user.surname', ''),
                ) ?: strcasecmp(
                    data_get($a->institutionUser, 'user.forename', ''),
                    data_get($b->institutionUser, 'user.forename', ''),
                );
            })
            ->first();
    }
}
