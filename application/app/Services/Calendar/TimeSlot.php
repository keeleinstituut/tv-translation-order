<?php

namespace App\Services\Calendar;

use Illuminate\Support\Carbon;

readonly class TimeSlot
{
    public function __construct(
        public Carbon $startAt,
        public Carbon $endAt,
        public Carbon $bufferedStartAt,
        public Carbon $bufferedEndAt,
    ) {}

    public static function forEvent(Carbon $startAt, Carbon $endAt): self
    {
        return new self($startAt, $endAt, $startAt, $endAt);
    }
}
