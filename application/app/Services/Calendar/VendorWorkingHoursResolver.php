<?php

namespace App\Services\Calendar;

use Illuminate\Support\Carbon;

class VendorWorkingHoursResolver
{
    public const array WORKTIME_COLUMNS = [
        'worktime_timezone',
        'monday_worktime_start', 'monday_worktime_end',
        'tuesday_worktime_start', 'tuesday_worktime_end',
        'wednesday_worktime_start', 'wednesday_worktime_end',
        'thursday_worktime_start', 'thursday_worktime_end',
        'friday_worktime_start', 'friday_worktime_end',
        'saturday_worktime_start', 'saturday_worktime_end',
        'sunday_worktime_start', 'sunday_worktime_end',
    ];

    private const array WORKTIME_DAYS = [
        'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday',
    ];

    /**
     * Return the UTC timestamp boundaries [start, end] of the working window
     * that falls inside the given slot. Returns null if the vendor has no
     * working hours in this slot (or the overlap is zero).
     *
     * @param  array{worktime_timezone: ?string, ...}|null  $institutionUserWorktime
     * @param  array{worktime_timezone: ?string, ...}|null  $institutionWorktime
     * @return array{0: int, 1: int}|null  [startTimestamp, endTimestamp] or null
     */
    public function workingWindowInSlot(
        ?array  $institutionUserWorktime,
        ?array  $institutionWorktime,
        Carbon  $slotStart,
        Carbon  $slotEnd,
    ): ?array
    {
        $source = $this->resolveSource($institutionUserWorktime, $institutionWorktime);

        if ($source === null) {
            return null;
        }

        $timezone = $source['worktime_timezone'] ?? null;

        if (blank($timezone)) {
            return null;
        }

        $slotStartInTz = $slotStart->copy()->setTimezone($timezone);
        $day = strtolower($slotStartInTz->format('l'));

        $startField = "{$day}_worktime_start";
        $endField = "{$day}_worktime_end";

        $worktimeStart = $source[$startField] ?? null;
        $worktimeEnd = $source[$endField] ?? null;

        if (blank($worktimeStart) || blank($worktimeEnd)) {
            return null;
        }

        $date = $slotStartInTz->toDateString();
        $windowStart = Carbon::parse("{$date} {$worktimeStart}", $timezone)->utc();
        $windowEnd = Carbon::parse("{$date} {$worktimeEnd}", $timezone)->utc();

        $overlapStart = max($slotStart->timestamp, $windowStart->timestamp);
        $overlapEnd = min($slotEnd->timestamp, $windowEnd->timestamp);

        if ($overlapEnd <= $overlapStart) {
            return null;
        }

        return [$overlapStart, $overlapEnd];
    }

    /**
     * Return the UTC timestamp boundaries [start, end] of the working window
     * for a full day. Convenience wrapper around workingWindowInSlot().
     *
     * @param  array{worktime_timezone: ?string, ...}|null  $institutionUserWorktime
     * @param  array{worktime_timezone: ?string, ...}|null  $institutionWorktime
     * @return array{0: int, 1: int}|null  [startTimestamp, endTimestamp] or null
     */
    public function getWorkingWindow(
        ?array  $institutionUserWorktime,
        ?array  $institutionWorktime,
        Carbon  $date,
    ): ?array
    {
        return $this->workingWindowInSlot(
            $institutionUserWorktime,
            $institutionWorktime,
            $date->copy()->startOfDay()->utc(),
            $date->copy()->endOfDay()->utc(),
        );
    }

    /**
     * Choose the effective worktime source.
     * Institution user takes priority if it has at least one non-null time field.
     *
     * @return array{worktime_timezone: ?string, ...}|null
     */
    private function resolveSource(?array $institutionUser, ?array $institution): ?array
    {
        $hasAnyWorktimeAttribute = $institutionUser !== null &&
            array_any(self::WORKTIME_DAYS, fn($day) => filled($institutionUser["{$day}_worktime_start"] ?? null));

        if ($hasAnyWorktimeAttribute) {
            return $institutionUser;
        }

        return $institution;
    }
}
