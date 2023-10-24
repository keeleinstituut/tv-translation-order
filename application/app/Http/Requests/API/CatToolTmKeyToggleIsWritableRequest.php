<?php

namespace App\Http\Requests\API;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: [
            'is_writable',
        ],
        properties: [
            new OA\Property(property: 'is_writable', type: 'boolean'),
        ]
    )
)]
class CatToolTmKeyToggleIsWritableRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'is_writable' => ['required', 'boolean'],
        ];
    }
}
