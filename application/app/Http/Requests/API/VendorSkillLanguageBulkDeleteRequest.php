<?php

namespace App\Http\Requests\API;

use App\Models\VendorSkillLanguage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: ['id'],
        properties: [
            new OA\Property(
                property: 'id',
                type: 'array',
                items: new OA\Items(type: 'string', format: 'uuid'),
                minItems: 1
            ),
        ]
    )
)]
class VendorSkillLanguageBulkDeleteRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'id' => 'required|array|min:1',
            'id.*' => [
                'uuid',
                'distinct',
                Rule::exists(VendorSkillLanguage::class, 'id'),
            ],
        ];
    }
}
