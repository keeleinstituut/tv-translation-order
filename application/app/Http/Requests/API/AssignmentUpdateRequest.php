<?php

namespace App\Http\Requests\API;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'comments', type: 'string', nullable: true),
            new OA\Property(property: 'deadline_at', type: 'string', format: 'date-time', example: '2020-12-31T12:00:00Z'),
        ]
    )
)]
class AssignmentUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'comments' => ['nullable', 'string'],
            'deadline_at' => ['required', 'date_format:Y-m-d\\TH:i:s\\Z'], // only UTC (zero offset)
        ];
    }
}
