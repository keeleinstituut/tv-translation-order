<?php

namespace App\Services\Calendar;

use App\Helpers\IntervalsUtil;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

readonly class SlotDiscretizationService
{
    /**
     * Fan out per-vendor free intervals into per-language merged intervals.
     *
     * Example:
     *   Input:  ['v1' => [[100, 300], [500, 700]], 'v2' => [[200, 600]]]
     *           CalendarData maps: v1 → ['lang-en'], v2 → ['lang-en', 'lang-de']
     *   Output: [
     *     'lang-en' => [[100, 700]],        // merged: v1[100,300]+v2[200,600]+v1[500,700]
     *     'lang-de' => [[200, 600]],         // only v2 covers German
     *   ]
     *
     * @param array<string, array<array{0: int, 1: int}>> $vendorFreeIntervals vendor_id => intervals
     * @param CalendarData $data used for vendor->language mapping
     * @return array<string, array<array{0: int, 1: int}>>  language_id => merged intervals
     */
    public function fanOutByLanguage(array $vendorFreeIntervals, CalendarData $data): array
    {
        $perLanguage = [];

        foreach ($vendorFreeIntervals as $vendorId => $intervals) {
            foreach ($data->getLanguagesForVendor($vendorId) as $langId) {
                if (!isset($perLanguage[$langId])) {
                    $perLanguage[$langId] = [];
                }
                array_push($perLanguage[$langId], ...$intervals);
            }
        }

        foreach ($perLanguage as $langId => $intervals) {
            $perLanguage[$langId] = IntervalsUtil::mergeIntervals($intervals);
        }

        return $perLanguage;
    }

    /**
     * Sweep per-language intervals and discretize into 1h slots tagged with language IDs.
     *
     * Example (timestamps chosen for readability, 3600 = 1h):
     *   Input:  ['lang-en' => [[0, 7200]], 'lang-de' => [[3600, 10800]]]
     *   Output: [
     *     ['start_at' => '1970-01-01T00:00:00+00:00', 'end_at' => '1970-01-01T01:00:00+00:00', 'languages' => ['lang-en']],
     *     ['start_at' => '1970-01-01T01:00:00+00:00', 'end_at' => '1970-01-01T02:00:00+00:00', 'languages' => ['lang-de', 'lang-en']],
     *     ['start_at' => '1970-01-01T02:00:00+00:00', 'end_at' => '1970-01-01T03:00:00+00:00', 'languages' => ['lang-de']],
     *   ]
     *
     * @param array<string, array<array{0: int, 1: int}>> $perLanguageIntervals language_id → intervals
     * @return array<array{start_at: string, end_at: string, languages: string[]}>
     */
    public function discretizeLanguageSlots(array $perLanguageIntervals): array
    {
        return $this->discretizeIntoHourSlots(
            $this->sweepLanguageIntervals($perLanguageIntervals)
        );
    }

    /**
     * Sweep-line across per-language intervals to produce non-overlapping
     * intervals tagged with active language ID sets.
     *
     * Example:
     *   Input:  ['lang-en' => [[0, 7200]], 'lang-de' => [[3600, 10800]]]
     *   Output: [
     *     ['start_at_ts' => 0,    'end_at_ts' => 3600,  'languages' => ['lang-en']],
     *     ['start_at_ts' => 3600, 'end_at_ts' => 7200,  'languages' => ['lang-de', 'lang-en']],
     *     ['start_at_ts' => 7200, 'end_at_ts' => 10800, 'languages' => ['lang-de']],
     *   ]
     *
     * @param array<string, array<array{0: int, 1: int}>> $perLanguageIntervals language_id → intervals
     * @return array<array{start_at_ts: int, end_at_ts: int, languages: string[]}>
     */
    private function sweepLanguageIntervals(array $perLanguageIntervals): array
    {
        if (empty($perLanguageIntervals)) {
            return [];
        }

        $events = [];
        foreach ($perLanguageIntervals as $languageId => $intervals) {
            foreach ($intervals as [$start, $end]) {
                $events[] = [$start, 1, $languageId];
                $events[] = [$end, 0, $languageId];
            }
        }

        usort($events, fn($a, $b) => $a[0] <=> $b[0] ?: $a[1] <=> $b[1]);

        $activeLanguages = [];
        $prevTime = null;
        $result = [];

        foreach ($events as [$time, $type, $languageId]) {
            if ($prevTime !== null && $time > $prevTime && !empty($activeLanguages)) {
                $languages = array_keys($activeLanguages);
                sort($languages);

                $lastIdx = count($result) - 1;
                if ($lastIdx >= 0 && $result[$lastIdx]['end_at_ts'] === $prevTime && $result[$lastIdx]['languages'] === $languages) {
                    $result[$lastIdx]['end_at_ts'] = $time;
                } else {
                    $result[] = [
                        'start_at_ts' => $prevTime,
                        'end_at_ts' => $time,
                        'languages' => $languages,
                    ];
                }
            }

            if ($type === 1) {
                $activeLanguages[$languageId] = ($activeLanguages[$languageId] ?? 0) + 1;
            } else {
                $activeLanguages[$languageId]--;
                if ($activeLanguages[$languageId] === 0) {
                    unset($activeLanguages[$languageId]);
                }
            }

            $prevTime = $time;
        }

        return $result;
    }

    /**
     * Discretize language-tagged intervals into slots clipped to hour boundaries.
     *
     * Full 1h slots are emitted for middle portions; shorter partial slots are
     * emitted at interval boundaries. Since input intervals (from sweepLanguageIntervals)
     * are already non-overlapping, the output is also non-overlapping.
     *
     * Because fanOutByLanguage merges per-language intervals before discretization,
     * partial slots only appear when NO vendor covers the full hour for that language.
     *
     * Example:
     *   Input:  [
     *     ['start_at_ts' => 0, 'end_at_ts' => 5400, 'languages' => ['lang-en']],  // 0–1.5h
     *   ]
     *   Output: [
     *     ['start_at' => '1970-01-01T00:00:00+00:00', 'end_at' => '1970-01-01T01:00:00+00:00', 'languages' => ['lang-en']],
     *     ['start_at' => '1970-01-01T01:00:00+00:00', 'end_at' => '1970-01-01T01:30:00+00:00', 'languages' => ['lang-en']],
     *   ]
     *
     * @param array<array{start_at_ts: int, end_at_ts: int, languages: string[]}> $slots
     * @return array<array{start_at: string, end_at: string, languages: string[]}>
     */
    private function discretizeIntoHourSlots(array $slots): array
    {
        $result = [];
        foreach ($slots as $slot) {
            $start = $slot['start_at_ts'];
            $end = $slot['end_at_ts'];
            $cursor = intdiv($start, 3600) * 3600;

            // Partial slot at the start (interval starts mid-hour)
            if ($cursor < $start) {
                $slotEnd = min($cursor + 3600, $end);
                $result[] = [
                    'start_at' => Carbon::createFromTimestamp($start)->utc()->toIso8601String(),
                    'end_at' => Carbon::createFromTimestamp($slotEnd)->utc()->toIso8601String(),
                    'languages' => $slot['languages'],
                ];
                $cursor += 3600;
            }

            // Full hour slots in the middle
            while ($cursor + 3600 <= $end) {
                $result[] = [
                    'start_at' => Carbon::createFromTimestamp($cursor)->utc()->toIso8601String(),
                    'end_at' => Carbon::createFromTimestamp($cursor + 3600)->utc()->toIso8601String(),
                    'languages' => $slot['languages'],
                ];
                $cursor += 3600;
            }

            // Partial slot at the end (interval ends mid-hour)
            if ($cursor < $end) {
                $result[] = [
                    'start_at' => Carbon::createFromTimestamp($cursor)->utc()->toIso8601String(),
                    'end_at' => Carbon::createFromTimestamp($end)->utc()->toIso8601String(),
                    'languages' => $slot['languages'],
                ];
            }
        }

        return $result;
    }

    /**
     * Discretize per-vendor free intervals into slots with vendor UUIDs.
     *
     * Clips each vendor's free intervals to hour boundaries, emitting full 1h slots
     * for middle portions and shorter partial slots at interval boundaries.
     * Slots from different vendors may overlap when their available ranges differ.
     *
     * Example:
     *   Input:  ['v1' => [[0, 7200]], 'v2' => [[1800, 7200]]]
     *   Output: [
     *     ['start_at' => '1970-01-01T00:00:00+00:00', 'end_at' => '1970-01-01T01:00:00+00:00', 'vendor_ids' => ['v1']],
     *     ['start_at' => '1970-01-01T00:30:00+00:00', 'end_at' => '1970-01-01T01:00:00+00:00', 'vendor_ids' => ['v2']],
     *     ['start_at' => '1970-01-01T01:00:00+00:00', 'end_at' => '1970-01-01T02:00:00+00:00', 'vendor_ids' => ['v1', 'v2']],
     *   ]
     *
     * @param array<string, array<array{0: int, 1: int}>> $vendorFreeIntervals
     * @return array<array{start_at: string, end_at: string, vendor_ids: string[]}>
     */
    public function discretizeWithVendorIds(array $vendorFreeIntervals): array
    {
        $slots = [];

        foreach ($vendorFreeIntervals as $vendorId => $intervals) {
            foreach ($intervals as [$start, $end]) {
                $cursor = intdiv($start, 3600) * 3600;

                // Partial slot at the start (interval starts mid-hour)
                if ($cursor < $start) {
                    $slotEnd = min($cursor + 3600, $end);
                    $key = $start . ':' . $slotEnd;
                    $slots[$key]['start'] = $start;
                    $slots[$key]['end'] = $slotEnd;
                    $slots[$key]['vendor_ids'][] = $vendorId;
                    $cursor += 3600;
                }

                // Full hour slots in the middle
                while ($cursor + 3600 <= $end) {
                    $key = $cursor . ':' . ($cursor + 3600);
                    $slots[$key]['start'] = $cursor;
                    $slots[$key]['end'] = $cursor + 3600;
                    $slots[$key]['vendor_ids'][] = $vendorId;
                    $cursor += 3600;
                }

                // Partial slot at the end (interval ends mid-hour)
                if ($cursor < $end) {
                    $key = $cursor . ':' . $end;
                    $slots[$key]['start'] = $cursor;
                    $slots[$key]['end'] = $end;
                    $slots[$key]['vendor_ids'][] = $vendorId;
                }
            }
        }

        uasort($slots, fn($a, $b) => $a['start'] <=> $b['start'] ?: $a['end'] <=> $b['end']);

        $result = [];
        foreach ($slots as $slot) {
            $result[] = [
                'start_at' => Carbon::createFromTimestamp($slot['start'])->utc()->toIso8601String(),
                'end_at' => Carbon::createFromTimestamp($slot['end'])->utc()->toIso8601String(),
                'vendor_ids' => array_values(array_unique($slot['vendor_ids'])),
            ];
        }

        return $result;
    }

    /**
     * Compute availability per language per slot for week view.
     *
     * Example:
     *   $coverageByLanguage = collect(['lang-en' => collect([(object)['vendor_id' => 'v1'], (object)['vendor_id' => 'v2']])])
     *   $precomputedAvailability = ['v1' => [0 => true], 'v2' => [0 => false]]
     *   $slotIndex = 0, $institutionId = 'inst-1'
     *
     *   Output (Collection): [
     *     ['institution_id' => 'inst-1', 'language_id' => 'lang-en', 'slot_start' => '…', 'slot_end' => '…',
     *      'total_vendors' => 2, 'available_vendors' => 1, 'available_vendor_ids' => ['v1']],
     *   ]
     *
     * @param array<string, array<int, bool>> $precomputedAvailability vendorId => [slotIndex => hasSlot]
     */
    public function computeSlotLanguageAvailability(
        Collection $coverageByLanguage,
        array      $precomputedAvailability,
        Carbon     $slotStart,
        Carbon     $slotEnd,
        int        $slotIndex,
        string     $institutionId,
    ): Collection
    {
        return $coverageByLanguage
            ->map(function ($vendorRows, $languageId) use (
                $slotStart, $slotEnd, $precomputedAvailability, $slotIndex, $institutionId,
            ) {
                $vendorIds = $vendorRows->pluck('vendor_id')->unique();

                $availableVendorIds = $vendorIds
                    ->filter(fn($id) => $precomputedAvailability[$id][$slotIndex] ?? false)
                    ->values();

                return [
                    'institution_id' => $institutionId,
                    'language_id' => $languageId,
                    'slot_start' => $slotStart->toIso8601String(),
                    'slot_end' => $slotEnd->toIso8601String(),
                    'total_vendors' => $vendorIds->count(),
                    'available_vendors' => $availableVendorIds->count(),
                    'available_vendor_ids' => $availableVendorIds->all(),
                ];
            })->values();
    }
}
