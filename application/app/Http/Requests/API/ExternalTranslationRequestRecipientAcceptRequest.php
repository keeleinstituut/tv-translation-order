<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: false,
    content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'proposed_price', type: 'number', format: 'double', nullable: true),
            new OA\Property(property: 'response_comment', type: 'string', nullable: true),
        ]
    )
)]
class ExternalTranslationRequestRecipientAcceptRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'proposed_price' => 'nullable|numeric|min:0',
            'response_comment' => 'nullable|string',
        ];
    }
}
