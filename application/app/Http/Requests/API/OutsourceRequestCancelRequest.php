<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: ['cancellation_reason'],
        properties: [
            new OA\Property(property: 'cancellation_reason', type: 'string', maxLength: 1800),
        ]
    )
)]
class OutsourceRequestCancelRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'cancellation_reason' => 'required|string|max:1800',
        ];
    }
}
