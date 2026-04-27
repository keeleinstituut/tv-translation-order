<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: ['decline_comment'],
        properties: [
            new OA\Property(property: 'decline_comment', type: 'string'),
        ]
    )
)]
class ExternalTranslationRequestRecipientDeclineRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'decline_comment' => 'required|string',
        ];
    }
}
