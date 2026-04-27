<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: ['recipient_id'],
        properties: [
            new OA\Property(property: 'recipient_id', type: 'string', format: 'uuid'),
        ]
    )
)]
class ExternalTranslationRequestSelectRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'recipient_id' => 'required|uuid',
        ];
    }
}
