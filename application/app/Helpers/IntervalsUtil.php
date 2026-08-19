<?php

namespace App\Helpers;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class IntervalsUtil
{
    /**
     * Subtract booked intervals from a working window.
     *
     * @param  array{0: int, 1: int}|null  $workWindow  [startTs, endTs] or null
     * @param  Collection  $entries  Collection of objects with start_ts and end_ts
     * @return array<array{0: int, 1: int}>  Remaining free intervals
     */
    public static function subtractIntervals(?array $workWindow, Collection $entries): array
    {
        if ($workWindow === null) {
            return [];
        }

        [$windowStart, $windowEnd] = $workWindow;

        if ($entries->isEmpty()) {
            return [[$windowStart, $windowEnd]];
        }

        // Sort entries by start timestamp
        $sorted = $entries->sortBy('start_ts')->values();

        $free = [];
        $cursor = $windowStart;

        foreach ($sorted as $entry) {
            $entryStart = $entry['start_ts'];
            $entryEnd = $entry['end_ts'];

            if ($entryStart > $cursor) {
                $gapEnd = min($entryStart, $windowEnd);
                if ($gapEnd > $cursor) {
                    $free[] = [$cursor, $gapEnd];
                }
            }

            $cursor = max($cursor, $entryEnd);

            if ($cursor >= $windowEnd) {
                break;
            }
        }

        if ($cursor < $windowEnd) {
            $free[] = [$cursor, $windowEnd];
        }

        return $free;
    }

    /**
     * Generate all UTC-aligned slot starts within a date range.
     *
     * $hoursInSlot controls the slot width:
     *   6  → 00:00 | 06:00 | 12:00 | 18:00 UTC (week view)
     *   24 → 00:00 UTC per day (month view)
     *
     * @return Carbon[]
     */
    public static function generateSlots(Carbon $from, Carbon $to, int $hoursInSlot = 6): array
    {
        $utcFrom = $from->copy()->utc();
        $cursor = $utcFrom->copy()->setTime(
            (int) floor($utcFrom->hour / $hoursInSlot) * $hoursInSlot, 0, 0
        );

        $slots = [];
        while ($cursor->lte($to)) {
            $slots[] = $cursor->copy();
            $cursor->addHours($hoursInSlot);
        }

        return $slots;
    }

    /**
     * Merge overlapping or adjacent intervals.
     *
     * @param  array<array{0: int, 1: int}>  $intervals
     * @return array<array{0: int, 1: int}>
     */
    public static function mergeIntervals(array $intervals): array
    {
        if (empty($intervals)) {
            return [];
        }

        usort($intervals, fn ($a, $b) => $a[0] <=> $b[0]);

        $merged = [$intervals[0]];

        for ($i = 1; $i < count($intervals); $i++) {
            $last = &$merged[count($merged) - 1];

            if ($intervals[$i][0] <= $last[1]) {
                $last[1] = max($last[1], $intervals[$i][1]);
            } else {
                $merged[] = $intervals[$i];
            }
        }

        return $merged;
    }
}
