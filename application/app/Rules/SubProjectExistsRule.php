<?php

namespace App\Rules;

use App\Models\SubProject;
use App\Policies\SubProjectPolicy;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Translation\PotentiallyTranslatedString;

class SubProjectExistsRule implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  \Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $exists = SubProject::withGlobalScope('policy', SubProjectPolicy::scope())
            ->where('id', $value)->exists();

        if (! $exists) {
            $fail("The sub-project with the id '$value' does not exist.");
        }
    }
}
