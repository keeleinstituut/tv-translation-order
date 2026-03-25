<?php

namespace App\Services\Calendar;

use App\Helpers\IntervalsUtil;
use App\Models\VendorCalendarEntry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

readonly class VendorsAvailabilityService
{
    public function __construct(
        private VendorWorkingHoursResolver $workingHoursResolver,
    ) {}

    /**
     * Compute working windows for all imported calendar vendors on a given day.
     *
     * @param  Collection|null  $excludeVendorIds  vendor IDs to skip (e.g. emergency)
     * @return array<string, array{0: int, 1: int}>  vendor_id => [startTs, endTs]
     */
    public function computeVendorWindows(
        CalendarData $data,
        Carbon       $date,
        ?Collection  $excludeVendorIds = null,
    ): array {
        $vendorWindows = [];

        foreach ($data->importedCalendarVendorIds as $vendorId) {
            if ($excludeVendorIds?->contains($vendorId)) {
                continue;
            }

            $window = $this->workingHoursResolver->getWorkingWindow(
                $data->getVendorWorktime($vendorId),
                $data->institutionWorktime,
                $date,
            );

            if ($window !== null) {
                $vendorWindows[$vendorId] = $window;
            }
        }

        return $vendorWindows;
    }

    /**
     * Compute free intervals per unique vendor for a day.
     * work_window - entries = free intervals.
     *
     * @param  array<string, array{0: int, 1: int}>|null  $vendorWindows  precomputed; when null, computed internally
     * @return array<string, array<array{0: int, 1: int}>>  vendor_id => free intervals
     */
    public function computeFreeIntervals(
        CalendarData $data,
        Carbon       $date,
        ?array       $vendorWindows = null,
        ?Collection  $excludeVendorIds = null,
    ): array {
        $result = [];
        $seen = [];

        $start = $date->copy()->startOfDay()->utc();
        $end = $date->copy()->endOfDay()->utc();

        foreach ($data->importedCalendarVendorIds as $vendorId) {
            if (isset($seen[$vendorId])) {
                continue;
            }
            $seen[$vendorId] = true;

            if ($excludeVendorIds?->contains($vendorId)) {
                continue;
            }

            $workWindow = $vendorWindows !== null
                ? ($vendorWindows[$vendorId] ?? null)
                : $this->workingHoursResolver->getWorkingWindow(
                    $data->getVendorWorktime($vendorId),
                    $data->institutionWorktime,
                    $date,
                );

            if ($workWindow === null) {
                continue;
            }

            $freeIntervals = IntervalsUtil::subtractIntervals(
                $workWindow,
                $this->getEntriesForVendor($vendorId, $start, $end),
            );

            if (!empty($freeIntervals)) {
                $result[$vendorId] = $freeIntervals;
            }
        }

        return $result;
    }

    /**
     * Compute free intervals for a language across a multi-day range.
     * Queries entries once per vendor, iterates days internally.
     *
     * @return array<string, array<array{0: int, 1: int}>>  vendor_id => free intervals (all days combined)
     */
    public function computeFreeIntervalsForLanguageInRange(
        CalendarData $data,
        Carbon       $start,
        Carbon       $end,
        string       $languageId,
        ?Collection  $excludeVendorIds = null,
    ): array {
        $result = [];
        $seen = [];

        foreach ($data->importedCalendarVendorIds as $vendorId) {
            if (isset($seen[$vendorId])) {
                continue;
            }
            $seen[$vendorId] = true;

            if (!$data->getLanguagesForVendor($vendorId)->contains($languageId)) {
                continue;
            }

            if ($excludeVendorIds?->contains($vendorId)) {
                continue;
            }

            $entries = $this->getEntriesForVendor($vendorId, $start, $end);
            $vendorIntervals = [];

            $currentDate = $start->copy()->startOfDay();
            $rangeEnd = $end->copy()->startOfDay();

            while ($currentDate->lte($rangeEnd)) {
                $workWindow = $this->workingHoursResolver->getWorkingWindow(
                    $data->getVendorWorktime($vendorId),
                    $data->institutionWorktime,
                    $currentDate,
                );

                if ($workWindow !== null) {
                    $freeIntervals = IntervalsUtil::subtractIntervals($workWindow, $entries);
                    array_push($vendorIntervals, ...$freeIntervals);
                }

                $currentDate->addDay();
            }

            if (!empty($vendorIntervals)) {
                $result[$vendorId] = $vendorIntervals;
            }
        }

        return $result;
    }

    /**
     * For each vendor and slot, determine whether the vendor has >= 1h free.
     *
     * @param  array<Carbon>  $slots  chronologically ordered slot start times
     * @return array<string, array<int, bool>>  vendorId => [slotIndex => hasAvailableSlot]
     */
    public function computeSlotAvailability(
        CalendarData $data,
        array        $slots,
        bool         $excludeEmergency = false,
    ): array {
        if (empty($slots)) {
            return [];
        }

        $start = $slots[0];
        $end = end($slots)->copy()->addHours(6);

        $result = [];

        foreach ($data->importedCalendarVendorIds as $vendorId) {
            $entries = $this->getEntriesForVendor($vendorId, $start, $end)
                ->sortBy('start_ts')->values()->all();
            $vendorWorktime = $data->getVendorWorktime($vendorId);
            $cursor = 0;
            $vendorAvailability = [];

            foreach ($slots as $slotIndex => $slotStart) {
                if ($excludeEmergency && $data->hasActiveEmergencySchedule($vendorId, $slotStart)) {
                    $vendorAvailability[$slotIndex] = false;
                    continue;
                }

                $slotEnd = $slotStart->copy()->addHours(6);

                $workWindow = $this->workingHoursResolver->workingWindowInSlot(
                    $vendorWorktime,
                    $data->institutionWorktime,
                    $slotStart,
                    $slotEnd,
                );

                if ($workWindow === null) {
                    $vendorAvailability[$slotIndex] = false;
                    continue;
                }

                $slotStartTs = $slotStart->timestamp;
                $slotEndTs = $slotStartTs + 6 * 3600;

                while ($cursor < count($entries) && $entries[$cursor]['end_ts'] <= $slotStartTs) {
                    $cursor++;
                }

                $slotEntries = collect();
                for ($i = $cursor; $i < count($entries); $i++) {
                    if ($entries[$i]['start_ts'] >= $slotEndTs) {
                        break;
                    }
                    $slotEntries->push($entries[$i]);
                }

                $freeIntervals = IntervalsUtil::subtractIntervals($workWindow, $slotEntries);

                $vendorAvailability[$slotIndex] = collect($freeIntervals)
                    ->contains(fn(array $interval) => ($interval[1] - $interval[0]) >= 3600);
            }

            $result[$vendorId] = $vendorAvailability;
        }

        return $result;
    }

    /**
     * Get overlapping entries as timestamp arrays for a single vendor.
     *
     * @return Collection<int, array{id: string, vendor_id: string, start_ts: int, end_ts: int}>
     */
    private function getEntriesForVendor(string $vendorId, Carbon $start, Carbon $end): Collection
    {
        return VendorCalendarEntry::where('vendor_id', $vendorId)
            ->overlapping($start, $end)
            ->get(['id', 'vendor_id', 'start_at', 'end_at'])
            ->map(fn(VendorCalendarEntry $entry) => [
                'id' => $entry->id,
                'vendor_id' => $entry->vendor_id,
                'start_ts' => Carbon::parse($entry->start_at)->utc()->timestamp,
                'end_ts' => Carbon::parse($entry->end_at)->utc()->timestamp,
            ]);
    }
}
