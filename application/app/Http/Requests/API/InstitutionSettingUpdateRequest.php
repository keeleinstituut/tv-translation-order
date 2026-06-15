<?php

namespace App\Http\Requests\API;

use App\Enums\ClassifierValueType;
use App\Models\CachedEntities\ClassifierValue;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        properties: [
            new OA\Property(property: 'reaction_time_minutes', type: 'integer', example: 30),
            new OA\Property(property: 'buffer_before_minutes', type: 'integer', example: 0),
            new OA\Property(property: 'buffer_after_minutes', type: 'integer', example: 0),
            new OA\Property(property: 'default_project_type_id', type: 'string', format: 'uuid'),
        ]
    )
)]
class InstitutionSettingUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'reaction_time_minutes' => ['sometimes', 'integer', 'min:0'],
            'buffer_before_minutes' => ['sometimes', 'integer', 'min:0'],
            'buffer_after_minutes' => ['sometimes', 'integer', 'min:0'],
            'default_project_type_id' => [
                'sometimes',
                'uuid',
                Rule::exists(ClassifierValue::class, 'id')
                    ->where('type', ClassifierValueType::ProjectType->value),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->has('default_project_type_id') && !ClassifierValue::isVerbalProjectType($this->input('default_project_type_id'))) {
                    $validator->errors()->add('default_project_type_id', 'Ainult suulise tõlke tüübid on lubatud');
                }
            }
        ];
    }
}
