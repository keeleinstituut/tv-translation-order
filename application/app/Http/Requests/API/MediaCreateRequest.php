<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\MediaType(
        mediaType: 'multipart/form-data',
        schema: new OA\Schema(
            required: ['files'],
            properties: [
                new OA\Property(
                    property: 'files',
                    type: 'array',
                    items: new OA\Items(
                        required: ['content', 'collection', 'reference_object_id', 'reference_object_type'],
                        properties: [
                            new OA\Property(property: 'content', type: 'string', format: 'binary'),
                            new OA\Property(property: 'collection', type: 'string'),
                            new OA\Property(property: 'reference_object_id', type: 'string', format: 'uuid'),
                            new OA\Property(property: 'reference_object_type', type: 'string'),
                        ]
                    ),
                    minItems: 1
                ),
            ]
        )
    )
)]
class MediaCreateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'files' => 'required|array|min:1',
            'files.*.content' => 'required|file',
            'files.*.collection' => 'required|string',
            'files.*.reference_object_id' => 'required|uuid',
            'files.*.reference_object_type' => 'required|string',
        ];
    }
}
