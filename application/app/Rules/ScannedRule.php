<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use App\Services\FileScanService;
use Illuminate\Translation\PotentiallyTranslatedString;

class ScannedRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $result = FileScanService::scanFiles([$value]);
        if ($result[0]['is_infected']) {
            $fail('Malicious file');
        }
    }

    public static function createRule(): self
    {
        return new self();
    }
}
