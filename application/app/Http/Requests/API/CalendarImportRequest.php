<?php

namespace App\Http\Requests\API;

use App\Rules\IcsFileValidator;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\MediaType(
        mediaType: 'multipart/form-data',
        schema: new OA\Schema(
            required: ['import_end_date', 'file'],
            properties: [
                new OA\Property(property: 'import_end_date', type: 'string', format: 'date', example: '2026-06-01'),
                new OA\Property(property: 'file', type: 'string', format: 'binary'),
            ]
        )
    )
)]
class CalendarImportRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'import_end_date' => ['required', 'date_format:Y-m-d', 'after:today'],
            'file' => ['required', IcsFileValidator::createRule()],
        ];
    }
}
