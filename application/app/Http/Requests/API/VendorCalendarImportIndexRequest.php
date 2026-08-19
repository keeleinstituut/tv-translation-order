<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class VendorCalendarImportIndexRequest extends FormRequest
{
    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'date_to' => ['sometimes', 'nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
