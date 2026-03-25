<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;

class MaxDateRange implements ValidationRule
{
    public function __construct(
        private readonly string $startField,
        private readonly int $maxDays,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $startAt = request($this->startField);

        if (!$startAt) {
            return;
        }

        if (!Carbon::canBeCreatedFromFormat($startAt, 'Y-m-d') || !Carbon::canBeCreatedFromFormat($value, 'Y-m-d')) {
            return;
        }

        $diff = Carbon::parse($startAt)->diffInDays(Carbon::parse($value));

        if ($diff > $this->maxDays) {
            $fail("The date range must not exceed {$this->maxDays} days.");
        }
    }
}
