<?php

namespace App\Http\Requests\API;

use App\Models\CachedEntities\ClassifierValue;
use App\Models\InstitutionPartner;
use App\Models\InstitutionPartnerPrice;
use App\Models\Skill;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use OpenApi\Attributes as OA;

#[OA\RequestBody(
    request: self::class,
    required: true,
    content: new OA\JsonContent(
        required: [
            'institution_partner_id', 'skill_id',
            'src_lang_classifier_value_id', 'dst_lang_classifier_value_id',
            'character_fee', 'word_fee', 'page_fee', 'minute_fee', 'hour_fee', 'minimal_fee',
        ],
        properties: [
            new OA\Property(property: 'institution_partner_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'skill_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'src_lang_classifier_value_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'dst_lang_classifier_value_id', type: 'string', format: 'uuid'),
            new OA\Property(property: 'character_fee', type: 'number', format: 'double'),
            new OA\Property(property: 'word_fee', type: 'number', format: 'double'),
            new OA\Property(property: 'page_fee', type: 'number', format: 'double'),
            new OA\Property(property: 'minute_fee', type: 'number', format: 'double'),
            new OA\Property(property: 'hour_fee', type: 'number', format: 'double'),
            new OA\Property(property: 'minimal_fee', type: 'number', format: 'double'),
        ]
    )
)]
class InstitutionPartnerPriceCreateRequest extends FormRequest
{
    public function rules(): array
    {
        $feeRule = 'required|decimal:0,3|between:0,99999999.99';

        return [
            'institution_partner_id' => [
                'required',
                'uuid',
                Rule::exists(InstitutionPartner::class, 'id')
                    ->where('institution_id', Auth::user()->institutionId),
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
                $existing = InstitutionPartnerPrice::query()
                    ->where('institution_partner_id', $this->input('institution_partner_id'))
                    ->where('skill_id', $this->input('skill_id'))
                    ->where('src_lang_classifier_value_id', $this->input('src_lang_classifier_value_id'))
                    ->where('dst_lang_classifier_value_id', $this->input('dst_lang_classifier_value_id'))
                    ->exists();

                if ($existing) {
                    $msg = 'Institution partner price already exists for this language pair and skill';
                    $validator->errors()
                        ->add('skill_id', $msg)
                        ->add('src_lang_classifier_value_id', $msg)
                        ->add('dst_lang_classifier_value_id', $msg);
                }
            },
        ];
    }
}
