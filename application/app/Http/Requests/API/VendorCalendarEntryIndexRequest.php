<?php

namespace App\Http\Requests\API;

use App\Enums\VendorCalendarEntryType;
use App\Rules\MaxDateRange;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VendorCalendarEntryIndexRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('assignments_only')) {
            $this->merge([
                'assignments_only' => filter_var($this->input('assignments_only'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE),
            ]);
        }
    }

    public function rules(): array
    {
        return [
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to' => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from', new MaxDateRange('date_from', 93)],
            'assignments_only' => ['sometimes', 'boolean'],
            'type' => ['sometimes', 'string', Rule::enum(VendorCalendarEntryType::class)],
        ];
    }
}
