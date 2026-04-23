<?php

namespace App\Http\Requests\API;

use App\Models\CachedEntities\ClassifierValue;
use App\Models\Skill;
use App\Models\Vendor;
use App\Models\VendorSkillLanguagePair;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class VendorSkillLanguagePairCreateRequest extends FormRequest
{
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
                $existing = VendorSkillLanguagePair::query()
                    ->where('vendor_id', $this->vendor_id)
                    ->where('skill_id', $this->skill_id)
                    ->where('src_lang_classifier_value_id', $this->src_lang_classifier_value_id)
                    ->where('dst_lang_classifier_value_id', $this->dst_lang_classifier_value_id)
                    ->exists();

                if ($existing) {
                    $msg = 'Vendor skill language pair already exists';
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
