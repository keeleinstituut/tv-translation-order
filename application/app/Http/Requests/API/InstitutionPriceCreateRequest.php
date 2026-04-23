<?php

namespace App\Http\Requests\API;

use App\Models\CachedEntities\ClassifierValue;
use App\Models\InstitutionPrice;
use App\Models\Skill;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class InstitutionPriceCreateRequest extends FormRequest
{
    public function rules(): array
    {
        $feeRule = 'required|decimal:0,3|between:0,99999999.99';

        return [
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
            'character_fee' => $feeRule,
            'word_fee' => $feeRule,
            'page_fee' => $feeRule,
            'minute_fee' => $feeRule,
            'hour_fee' => $feeRule,
            'minimal_fee' => $feeRule,
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $existing = InstitutionPrice::query()
                    ->where('institution_id', Auth::user()->institutionId)
                    ->where('skill_id', $this->skill_id)
                    ->where('src_lang_classifier_value_id', $this->src_lang_classifier_value_id)
                    ->where('dst_lang_classifier_value_id', $this->dst_lang_classifier_value_id)
                    ->exists();

                if ($existing) {
                    $msg = 'Institution price already exists for this language pair and skill';
                    $validator->errors()
                        ->add('skill_id', $msg)
                        ->add('src_lang_classifier_value_id', $msg)
                        ->add('dst_lang_classifier_value_id', $msg);
                }
            },
        ];
    }
}
