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
            new OA\Property(property: 'cancellation_reason', type: 'string'),
            new OA\Property(property: 'cancellation_comment', type: 'string', nullable: true),
        ]
    )
)]
class ProjectCancelRequest extends FormRequest
{

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'cancellation_reason' => ['required', 'string'],
            'cancellation_comment' => ['nullable', 'string'],
        ];
    }
}
