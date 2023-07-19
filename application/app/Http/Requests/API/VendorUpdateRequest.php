<?php

namespace App\Http\Requests\API;

use App\Enums\TagType;
use App\Models\Tag;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: ['comment', 'tags'],
        properties: [
            new OA\Property(property: 'comment', type: 'string'),
            new OA\Property(
                property: 'tags',
                type: 'array',
                items: new OA\Items(
                    type: 'string',
                    format: 'uuid',
                ),
                minItems: 1
            ),
        ]
    )
)]
class VendorUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'tags' => 'sometimes|array',
            'tags.*' => [
                'required',
                Rule::exists(Tag::class, 'id')->where('type', TagType::Vendor->value),
            ],
            'comment' => 'sometimes|string',
        ];
    }
}
