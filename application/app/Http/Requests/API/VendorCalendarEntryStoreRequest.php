<?php

namespace App\Http\Requests\API;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: ['vendor_id', 'start_at', 'end_at'],
        properties: [
            new OA\Property(property: 'vendor_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'start_at', type: 'string', format: 'date-time', example: '2026-04-15T09:00:00Z'),
            new OA\Property(property: 'end_at', type: 'string', format: 'date-time', example: '2026-04-15T17:00:00Z'),
            new OA\Property(property: 'comment', type: 'string', nullable: true, example: 'Vendor is on sick leave'),
        ]
    )
)]
class VendorCalendarEntryStoreRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'vendor_id' => ['required', 'uuid'],
            'start_at' => ['required', 'date'],
            'end_at' => ['required', 'date', 'after:start_at'],
            'comment' => ['nullable', 'string', 'max:500'],
        ];
    }
}
