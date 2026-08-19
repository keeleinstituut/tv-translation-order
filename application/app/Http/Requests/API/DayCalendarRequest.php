<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class DayCalendarRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'date' => ['nullable', 'date_format:Y-m-d'],
        ];
    }
}
