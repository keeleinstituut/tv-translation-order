<?php

namespace App\Http\Requests\API;

use App\Rules\MaxDateRange;
use Illuminate\Foundation\Http\FormRequest;

class CalendarLanguagesRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'date_from' => ['required', 'date_format:Y-m-d'],
            'date_to'   => ['required', 'date_format:Y-m-d', 'after_or_equal:date_from', new MaxDateRange('date_from', 93)],
        ];
    }
}
