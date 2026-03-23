<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class CalendarSearchRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'language_id' => ['required', 'uuid'],
            'datetime' => ['nullable', 'date', 'after_or_equal:now'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:480'],
        ];
    }
}
