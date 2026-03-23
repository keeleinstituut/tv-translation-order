<?php

namespace App\Http\Requests\API;

use App\Enums\ClassifierValueType;
use App\Models\CachedEntities\ClassifierValue;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: ['languages'],
        properties: [
            new OA\Property(
                property: 'languages',
                description: 'Language classifier value IDs to set as institution main languages',
                type: 'array',
                items: new OA\Items(type: 'string', format: 'uuid'),
            ),
        ]
    )
)]
class SyncMainLanguagesRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'languages' => ['present', 'array'],
            'languages.*' => [
                'uuid',
                Rule::exists(ClassifierValue::class, 'id')
                    ->where('type', ClassifierValueType::Language->value),
            ],
        ];
    }
}
