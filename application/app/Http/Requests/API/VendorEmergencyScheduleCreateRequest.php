<?php

namespace App\Http\Requests\API;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: ['start_date', 'end_date'],
        properties: [
            new OA\Property(property: 'start_date', type: 'string', format: 'date', example: '2026-03-15'),
            new OA\Property(property: 'end_date', type: 'string', format: 'date', example: '2026-03-20'),
        ]
    )
)]
class VendorEmergencyScheduleCreateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'start_date' => ['required', 'date_format:Y-m-d'],
            'end_date' => ['required', 'date_format:Y-m-d', 'after_or_equal:start_date'],
        ];
    }
}
