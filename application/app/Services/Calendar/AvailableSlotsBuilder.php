<?php

namespace App\Services\Calendar;

use App\Helpers\IntervalsUtil;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

readonly class AvailableSlotsBuilder
{
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
     * @param array $vendorFreeIntervals
     * @param CalendarData $data
     * @return array<array{start_at_ts: int, end_at_ts: int, languages: string[]}>
     */
    public function languageTaggedFreeSlots(array $vendorFreeIntervals, CalendarData $data): array
    {
        $perLanguageIntervals = $this->fanOutByLanguage($vendorFreeIntervals, $data);
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

        return array_map(fn($s) => [
            'start_at' => Carbon::createFromTimestamp($s['start_at_ts'])->utc()->toIso8601String(),
            'end_at' => Carbon::createFromTimestamp($s['end_at_ts'])->utc()->toIso8601String(),
            'languages' => $s['languages'],
        ], $result);
    }

    /**
     * Group per-vendor free intervals into slots tagged with vendor UUIDs.
     *
     * Each vendor's free intervals are emitted as-is (continuous, no hour
     * chopping). Intervals identical across vendors collapse into one slot
     * with several vendor_ids; vendors with different ranges produce separate,
     * possibly overlapping slots.
     *
     * Example:
     *   Input:  ['v1' => [[0, 7200]], 'v2' => [[1800, 7200]]]
     *   Output: [
     *     ['start_at' => '1970-01-01T00:00:00+00:00', 'end_at' => '1970-01-01T02:00:00+00:00', 'vendor_ids' => ['v1']],
     *     ['start_at' => '1970-01-01T00:30:00+00:00', 'end_at' => '1970-01-01T02:00:00+00:00', 'vendor_ids' => ['v2']],
     *   ]
     *
     * @param array<string, array<array{0: int, 1: int}>> $vendorFreeIntervals
     * @return array<array{start_at: string, end_at: string, vendor_ids: string[]}>
     */
    public function vendorTaggedFreeSlots(array $vendorFreeIntervals): array
    {
        $slots = [];

        foreach ($vendorFreeIntervals as $vendorId => $intervals) {
            foreach ($intervals as [$start, $end]) {
                $key = $start . ':' . $end;
                $slots[$key]['start'] = $start;
                $slots[$key]['end'] = $end;
                $slots[$key]['vendor_ids'][] = $vendorId;
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
    private function fanOutByLanguage(array $vendorFreeIntervals, CalendarData $data): array
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
}
