<?php

namespace App\Http\Requests\API;

use App\Models\CachedEntities\ClassifierValue;
use App\Models\Skill;
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
            new OA\Property(property: 'id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'skill_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'src_lang_classifier_value_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'dst_lang_classifier_value_id', type: 'string', format: 'uuid'),
        ]
    )
)]
class VendorSkillLanguageUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'id' => [
                'required',
                Rule::exists(VendorSkillLanguage::class, 'id'),
            ],
            'skill_id' => [
                'sometimes',
                'uuid',
                Rule::exists(Skill::class, 'id'),
            ],
            'src_lang_classifier_value_id' => [
                'sometimes',
                'uuid',
                Rule::exists(ClassifierValue::class, 'id')->where('type', 'LANGUAGE'),
            ],
            'dst_lang_classifier_value_id' => [
                'sometimes',
                'uuid',
                'different:src_lang_classifier_value_id',
                Rule::exists(ClassifierValue::class, 'id')->where('type', 'LANGUAGE'),
            ],
        ];
    }
}
