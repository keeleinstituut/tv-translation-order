<?php

namespace App\Http\Requests\API;

use App\Models\CachedEntities\ClassifierValue;
use App\Models\Skill;
use App\Models\Vendor;
use App\Models\VendorSkillLanguage;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: ['vendor_id', 'skill_id', 'src_lang_classifier_value_id', 'dst_lang_classifier_value_id'],
        properties: [
            new OA\Property(property: 'vendor_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'skill_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'src_lang_classifier_value_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'dst_lang_classifier_value_id', type: 'string', format: 'uuid'),
        ]
    )
)]
class VendorSkillLanguageCreateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array|string>
     */
    public function rules(): array
    {
        return [
            'vendor_id' => [
                'required',
                'uuid',
                Rule::exists(Vendor::class, 'id'),
            ],
            'skill_id' => [
                'required',
                'uuid',
                Rule::exists(Skill::class, 'id'),
            ],
            'src_lang_classifier_value_id' => [
                'required',
                'uuid',
                Rule::exists(ClassifierValue::class, 'id')->where('type', 'LANGUAGE'),
            ],
            'dst_lang_classifier_value_id' => [
                'required',
                'uuid',
                'different:src_lang_classifier_value_id',
                Rule::exists(ClassifierValue::class, 'id')->where('type', 'LANGUAGE'),
            ],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $existing = VendorSkillLanguage::query()
                    ->where('vendor_id', $this->input('vendor_id'))
                    ->where('skill_id', $this->input('skill_id'))
                    ->where('src_lang_classifier_value_id', $this->input('src_lang_classifier_value_id'))
                    ->where('dst_lang_classifier_value_id', $this->input('dst_lang_classifier_value_id'))
                    ->exists();

                if ($existing) {
                    $msg = 'Vendor skill language already exists';
                    $validator->errors()
                        ->add('vendor_id', $msg)
                        ->add('skill_id', $msg)
                        ->add('src_lang_classifier_value_id', $msg)
                        ->add('dst_lang_classifier_value_id', $msg);
                }
            },
        ];
    }
}
